<?php

/*
 * Fails when a migration's up() changes the schema in a way the release still running cannot
 * survive. deploy.sh migrates from the new image while the old one keeps serving, so for a few
 * seconds old code runs on the new schema - and after a DROP or RENAME, rolling back to the
 * previous image no longer works at all. Such a change has to be split across two deploys
 * (expand/contract, see docs/database-migrations.md); when one is genuinely meant, the migration says so with a line
 *
 *     // destructive-migration: <why it is safe or how it was split>
 *
 * The check reads the SQL text Doctrine generates (uppercase keywords in addSql()), not a parse of
 * it, so it is a tripwire, not a proof: it catches the usual shapes, not every one.
 *
 * Usage: php .github/scripts/check-destructive-migrations.php [migrations directory]
 */

declare(strict_types=1);

// Migrations up to and including this one predate the rule and ran on production long ago.
const BASELINE = 'Version20260721120000';

const MARKER = '~//\s*destructive-migration:\s*\S~';

const RULES = [
	'~\bDROP\s+(?!FOREIGN\s+KEY\b|INDEX\b|CONSTRAINT\b|CHECK\b)~' => 'drops a table or a column',
	'~\bRENAME\s+(?!INDEX\b|KEY\b)~' => 'renames a table or a column',
	'~\bCHANGE\s+~' => 'redefines a column (CHANGE)',
	'~\bMODIFY\s+~' => 'redefines a column (MODIFY)',
];

// An ADD clause that is not a constraint or index, i.e. a new column, up to the next comma.
const ADD_COLUMN = '~\bADD\s+(?:COLUMN\s+)?(?!CONSTRAINT\b|INDEX\b|UNIQUE\b|PRIMARY\b|FOREIGN\b|KEY\b|FULLTEXT\b|SPATIAL\b|CHECK\b)\w+\s[^,\'"]*~';

/**
 * @return list<array{int, string}> offset and reason of every problem in the up() body
 */
function findProblems(string $upBody): array {
	$problems = [];
	foreach (RULES as $pattern => $reason) {
		preg_match_all($pattern, $upBody, $matches, PREG_OFFSET_CAPTURE);
		foreach ($matches[0] as [, $offset]) {
			$problems[] = [$offset, $reason];
		}
	}
	preg_match_all(ADD_COLUMN, $upBody, $matches, PREG_OFFSET_CAPTURE);
	foreach ($matches[0] as [$clause, $offset]) {
		if (str_contains($clause, 'NOT NULL') && !str_contains($clause, 'DEFAULT')) {
			$problems[] = [$offset, 'adds a NOT NULL column without a DEFAULT - INSERTs of the running release fail'];
		}
	}
	usort($problems, static fn(array $a, array $b): int => $a[0] <=> $b[0]);
	return $problems;
}

$directory = $argv[1] ?? 'migrations';
$annotate = getenv('GITHUB_ACTIONS') === 'true';
$failed = false;

foreach (glob("$directory/Version*.php") ?: [] as $file) {
	if (strcmp(basename($file, '.php'), BASELINE) <= 0) {
		continue;
	}
	$source = (string) file_get_contents($file);
	if (preg_match(MARKER, $source) === 1) {
		continue;
	}
	$start = strpos($source, 'function up(');
	if ($start === false) {
		continue;
	}
	$end = strpos($source, 'function down(', $start);
	if ($end === false) {
		$end = strlen($source);
	}
	foreach (findProblems(substr($source, $start, $end - $start)) as [$offset, $reason]) {
		$failed = true;
		$line = substr_count($source, "\n", 0, $start + $offset) + 1;
		$message = "up() $reason. Split it across two deploys, or mark the migration with '// destructive-migration: <reason>'.";
		if ($annotate) {
			echo "::error file=$file,line=$line::$message\n";
		} else {
			echo "$file:$line: $message\n";
		}
	}
}

if ($failed) {
	exit(1);
}
echo "OK - no unmarked destructive migration after " . BASELINE . ".\n";
