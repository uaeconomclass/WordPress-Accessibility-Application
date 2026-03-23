import fs from 'node:fs';
import path from 'node:path';
import { execFileSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import { chromium } from 'playwright';

const HERE = path.dirname(fileURLToPath(import.meta.url));
const E2E_DIR = path.resolve(HERE, '..');
const REPO_DIR = path.resolve(E2E_DIR, '..', '..');
const LAB_DIR = path.resolve(REPO_DIR, '..', 'wp-whittemore-lab');
const JSON_REPORT = path.join(REPO_DIR, 'tests', 'coverage', 'seed-detectability-report.json');
const MD_REPORT = path.join(REPO_DIR, 'tests', 'coverage', 'seed-detectability-report.md');
const BASE_URL = process.env.WP_URL || 'http://localhost:8090';
const HEADED = process.env.SWEEP_HEADED !== '0';
const SLOW_MO = Number(process.env.SWEEP_SLOW_MO || (HEADED ? 250 : 0));

function dockerCompose(args, options = {}) {
  return execFileSync('docker', ['compose', ...args], {
    cwd: LAB_DIR,
    encoding: 'utf8',
    stdio: options.stdio || 'pipe',
    ...options
  });
}

function parseJsonOutput(output) {
  const trimmed = String(output || '').trim();
  const lines = trimmed.split(/\r?\n/).map((line) => line.trim()).filter(Boolean);
  const jsonLine = [...lines].reverse().find((line) => line.startsWith('{') || line.startsWith('['));
  if (!jsonLine) {
    throw new Error(`Expected JSON output, got: ${trimmed}`);
  }
  return JSON.parse(jsonLine);
}

function wpJson(code) {
  return parseJsonOutput(
    dockerCompose(['exec', '-T', 'wordpress', 'php'], {
      input: `${code}\n`
    })
  );
}

function getCatalog() {
  return wpJson(`<?php
require '/var/www/html/wp-load.php';
if (!defined('AA_SEED_LIBRARY_ONLY')) define('AA_SEED_LIBRARY_ONLY', true);
require '/var/www/html/wp-content/plugins/accessibility-auditor/scripts/seed_bricks_test_pages.php';
$catalog = aa_seed_fixture_catalog();
echo wp_json_encode(array_values(array_map(static function($fixture) {
  return [
    'slug' => (string) $fixture['slug'],
    'scenario' => (string) $fixture['scenario'],
    'title' => (string) $fixture['title'],
    'strategy' => (string) $fixture['strategy'],
    'rule_ids' => array_values((array) $fixture['rule_ids']),
    'components' => array_values((array) $fixture['components']),
  ];
}, $catalog)));`);
}

function reseedScenario(scenario) {
  dockerCompose(
    [
      'run',
      '--rm',
      '-e',
      `AA_SEED_SCENARIO=${scenario}`,
      'wpcli',
      'eval-file',
      '/var/www/html/wp-content/plugins/accessibility-auditor/scripts/seed_bricks_test_pages.php',
      '--path=/var/www/html',
      '--allow-root'
    ],
    { stdio: 'inherit' }
  );
}

function getFixturePage(scenario) {
  return wpJson(`<?php
require '/var/www/html/wp-load.php';
$posts = get_posts([
  'post_type' => 'page',
  'post_status' => 'any',
  'posts_per_page' => 1,
  'meta_key' => '_aa_fixture_scenario',
  'meta_value' => '${scenario}'
]);
$post = $posts ? $posts[0] : null;
echo wp_json_encode([
  'post_id' => $post ? (int) $post->ID : 0,
  'title' => $post ? html_entity_decode((string) get_the_title($post), ENT_QUOTES) : '',
  'slug' => $post ? (string) $post->post_name : '',
]);`);
}

async function runFrontendAxe(page) {
  await page.addScriptTag({ url: 'https://cdnjs.cloudflare.com/ajax/libs/axe-core/4.10.2/axe.min.js' });
  return page.evaluate(async () => {
    const scope = document.querySelector('#brx-content') || document.body;
    const results = await window.axe.run(scope, { resultTypes: ['violations', 'incomplete'] });
    return {
      violation_ids: results.violations.map((row) => row.id),
      incomplete_ids: results.incomplete.map((row) => row.id),
      violation_nodes: results.violations.map((row) => row.nodes.length),
      incomplete_nodes: results.incomplete.map((row) => row.nodes.length),
      html_preview: (scope.innerHTML || '').slice(0, 1200),
      text_preview: (scope.innerText || '').slice(0, 400)
    };
  });
}

function classifyScenario(fixture, scan) {
  const foundIds = [...new Set([...(scan.violation_ids || []), ...(scan.incomplete_ids || [])].filter(Boolean))];
  const expectedIds = fixture.rule_ids || [];
  const matchedExpected = expectedIds.filter((id) => foundIds.includes(id));
  const detectable = matchedExpected.length > 0;
  const scanReady = detectable;
  const autofixReady = fixture.strategy === 'auto-fix' && matchedExpected.length > 0;

  let note = '';
  if (!detectable) {
    note = 'Expected rule did not appear inside #brx-content.';
  } else if (fixture.strategy === 'auto-fix') {
    note = 'Detected inside page-level content and eligible for auto-fix testing.';
  } else {
    note = 'Detected inside page-level content.';
  }

  return {
    detectable,
    scan_test_ready: scanReady,
    autofix_test_ready: autofixReady,
    matched_expected_rule_ids: matchedExpected,
    found_rule_ids: foundIds,
    note
  };
}

function writeReports(results) {
  fs.mkdirSync(path.dirname(JSON_REPORT), { recursive: true });
  fs.writeFileSync(JSON_REPORT, `${JSON.stringify(results, null, 2)}\n`, 'utf8');

  const lines = [
    '# Seed Detectability Report',
    '',
    `Generated: ${new Date().toISOString()}`,
    '',
    '| Scenario | Strategy | Expected Rules | Found Rules | Detectable | Scan Ready | Auto-fix Ready | Note |',
    '| --- | --- | --- | --- | --- | --- | --- | --- |'
  ];

  for (const row of results) {
    lines.push(
      `| ${row.scenario} | ${row.strategy} | ${row.expected_rule_ids.join(', ')} | ${row.found_rule_ids.join(', ') || '—'} | ${row.detectable ? 'yes' : 'no'} | ${row.scan_test_ready ? 'yes' : 'no'} | ${row.autofix_test_ready ? 'yes' : 'no'} | ${row.note} |`
    );
  }

  const readyScan = results.filter((row) => row.scan_test_ready).length;
  const readyAuto = results.filter((row) => row.autofix_test_ready).length;

  lines.push('', `Scan-ready: ${readyScan}/${results.length}`, `Auto-fix-ready: ${readyAuto}/${results.length}`, '');
  fs.writeFileSync(MD_REPORT, `${lines.join('\n')}\n`, 'utf8');
}

const browser = await chromium.launch({
  headless: !HEADED,
  slowMo: SLOW_MO,
  args: ['--start-maximized']
});

const context = await browser.newContext({ viewport: null });
const page = await context.newPage();
const catalog = getCatalog();
const results = [];

for (const fixture of catalog) {
  console.log(`\n=== ${fixture.scenario} ===`);
  reseedScenario(fixture.scenario);
  const seeded = getFixturePage(fixture.scenario);
  const record = {
    scenario: fixture.scenario,
    title: fixture.title,
    strategy: fixture.strategy,
    expected_rule_ids: fixture.rule_ids,
    components: fixture.components,
    post_id: seeded.post_id,
    slug: seeded.slug
  };

  try {
    await page.goto(`${BASE_URL}/${seeded.slug}/`, { waitUntil: 'domcontentloaded' });
    await page.waitForTimeout(1200);

    const frontendScan = await runFrontendAxe(page);
    const classification = classifyScenario(fixture, frontendScan);

    Object.assign(record, classification, {
      panel_state: classification.detectable ? 'issues' : 'no-issues',
      score: 0,
      grade: '',
      issue_titles: [],
      violation_ids: frontendScan.violation_ids,
      incomplete_ids: frontendScan.incomplete_ids,
      html_preview: frontendScan.html_preview,
      text_preview: frontendScan.text_preview
    });

    console.log(`${classification.detectable ? 'detectable' : 'missing'} | found=${classification.found_rule_ids.join(',') || '—'} | scan=${classification.scan_test_ready} | autofix=${classification.autofix_test_ready}`);
  } catch (error) {
    Object.assign(record, {
      detectable: false,
      scan_test_ready: false,
      autofix_test_ready: false,
      matched_expected_rule_ids: [],
      found_rule_ids: [],
      note: error?.message || String(error),
      panel_state: 'error',
      score: 0,
      grade: '',
      issue_titles: []
    });
    console.log(`ERROR: ${record.note}`);
  }

  results.push(record);
  await page.waitForTimeout(800);
}

writeReports(results);
console.log(`\nSaved:\n- ${JSON_REPORT}\n- ${MD_REPORT}`);

await page.waitForTimeout(HEADED ? 3000 : 0);
await browser.close();
