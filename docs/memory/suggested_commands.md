# Suggested Commands (Windows / PowerShell)

## Git
- `git status --short --branch`
- `git branch --show-current`
- `git log --oneline -n 10`
- `git fetch upstream`
- `git merge upstream/main`
- `git push -u origin development`

## Search / navigation
- `rg --files`
- `rg -n "pattern" classes assets`
- `Get-ChildItem -Recurse`

## Local WordPress env (wp-env)
- `npx @wordpress/env start`
- `npx @wordpress/env stop`
- `npx @wordpress/env destroy`

## Quick PHP validation
- `php -l accessibility-auditor.php`
- `php -l classes\AI.php`
- `Get-ChildItem classes -Filter *.php -Recurse | ForEach-Object { php -l $_.FullName }`

## Notes
- No dedicated lint/test command was discovered in repo config files.
- Use targeted smoke checks in wp-env for scan/AI endpoints after code changes.
