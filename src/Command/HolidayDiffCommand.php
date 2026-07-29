<?php

namespace App\Command;

use App\DTO\HolidayDiffRow;
use App\Enum\HolidayDiffStatus;
use App\Service\Import\HolidayDiffService;
use App\Service\Import\HolidayTextParser;
use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Compares a pasted list of holidays against the database and reports what is new, what moved and
 * what is already there. Writes nothing - see HolidayDiffService for why.
 *
 * Run it on the server that holds the database (the panel's host), so no tunnel is needed:
 *
 *     php bin/console app:holiday:diff blog.txt
 *     pbpaste | php bin/console app:holiday:diff
 */
#[AsCommand(name: 'app:holiday:diff', description: 'Compares a pasted holiday list against the stored Polish holidays')]
class HolidayDiffCommand extends Command
{
	private const string HELP = <<<'HELP'
		Paste a holiday list from a blog or forum and see what is new, what moved and what is already
		stored. Nothing is written to the database - this command only reports.

		  <info>php %command.full_name% blog.txt</info>          read a file
		  <info>pbpaste | php %command.full_name%</info>          read whatever is on the clipboard (macOS)
		  <info>php %command.full_name% < blog.txt</info>        read STDIN
		  <info>php %command.full_name% blog.txt --all</info>    also list the rows that already match
		  <info>php %command.full_name% blog.txt --json</info>   machine-readable output

		Run it on the host that holds the database, or open an SSH tunnel first:

		  <info>ssh -N -L 3307:127.0.0.1:3306 user@host</info>
		  and point DATABASE_URL in .env.local at 127.0.0.1:3307

		<comment>Every line is put in one of six buckets:</comment>

		  <info>exact</info>           name and date both agree - nothing to do
		  <info>date_mismatch</info>   the name is stored but under a different date - check which one is right
		  <info>floating_match</info>  the name is stored as a floating holiday, whose date is computed per year,
		                  so the pasted date cannot be compared - check by hand
		  <info>ambiguous</info>       a close but not conclusive name match - decide by hand
		  <info>missing</info>         no name close enough was found - a candidate to add
		  <info>unparsed</info>        the line looked like a date but could not be read

		Lines with no date shape at all are dropped silently, so blog prose does not drown the report.
		That includes recurrence rules ("trzeci czwartek listopada") - those are floating holidays, and
		this command will not invent a fixed date for one.

		Names are compared after folding Polish diacritics, stripping inflectional endings and dropping
		filler words ("Światowy", "Dzień", "Święto"), so "Światowy Dzień Kota" and "Święto Kotów" count
		as the same holiday.

		A month heading on its own line sets the month for the bare list items under it:

		  STYCZEŃ
		  1. Dzień Domeny Publicznej
		  2) Dzień Nauki Polskiej
		HELP;

	public function __construct(
		private readonly HolidayTextParser $parser,
		private readonly HolidayDiffService $diff
	) {
		parent::__construct();
	}

	#[Override]
	protected function configure(): void
	{
		$this->addArgument('file', InputArgument::OPTIONAL, 'File holding the pasted text; reads STDIN when omitted')
			->addOption('all', null, InputOption::VALUE_NONE, 'Also list the rows that already match exactly')
			->addOption('json', null, InputOption::VALUE_NONE, 'Print every row as JSON instead of a report')
			->setHelp(self::HELP);
	}

	#[Override]
	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$io = new SymfonyStyle($input, $output);
		$text = $this->read($input, $io);
		if ($text === null) {
			return Command::FAILURE;
		}

		$rows = $this->diff->diff($this->parser->parse($text));
		if ($rows === []) {
			$io->warning('No holiday lines were found in the input.');
			return Command::SUCCESS;
		}

		if ($input->getOption('json')) {
			$output->writeln($this->toJson($rows));
			return Command::SUCCESS;
		}
		$this->report($io, $rows, (bool)$input->getOption('all'));
		return Command::SUCCESS;
	}

	private function read(InputInterface $input, SymfonyStyle $io): ?string
	{
		$file = $input->getArgument('file');
		if ($file === null) {
			// Without this the command blocks on a terminal forever, looking like a hang: reading
			// STDIN is only meaningful when something was piped or redirected into it.
			if (stream_isatty(STDIN)) {
				$io->error('No input. Pass a file, or pipe the pasted text in - see --help.');
				return null;
			}
			$text = stream_get_contents(STDIN);
			if ($text === false || trim($text) === '') {
				$io->error('Nothing on STDIN. Pass a file, or pipe the pasted text in.');
				return null;
			}
			return $text;
		}
		if (!is_readable($file)) {
			$io->error(sprintf('Cannot read "%s".', $file));
			return null;
		}
		$text = file_get_contents($file);
		if ($text === false) {
			$io->error(sprintf('Cannot read "%s".', $file));
			return null;
		}
		return $text;
	}

	/**
	 * @param HolidayDiffRow[] $rows
	 */
	private function report(SymfonyStyle $io, array $rows, bool $all): void
	{
		$io->title('Holiday diff');
		$summary = [];
		foreach (HolidayDiffStatus::cases() as $status) {
			$summary[] = [$status->value, count($this->ofStatus($rows, $status)), $status->label()];
		}
		$io->table(['Status', 'Count', 'Meaning'], $summary);

		$this->section($io, $rows, HolidayDiffStatus::DATE_MISMATCH);
		$this->section($io, $rows, HolidayDiffStatus::AMBIGUOUS);
		$this->section($io, $rows, HolidayDiffStatus::FLOATING_MATCH);
		$this->section($io, $rows, HolidayDiffStatus::MISSING);
		$this->section($io, $rows, HolidayDiffStatus::UNPARSED);
		if ($all) {
			$this->section($io, $rows, HolidayDiffStatus::EXACT);
		}
	}

	/**
	 * @param HolidayDiffRow[] $rows
	 */
	private function section(SymfonyStyle $io, array $rows, HolidayDiffStatus $status): void
	{
		$selected = $this->ofStatus($rows, $status);
		if ($selected === []) {
			return;
		}
		$io->section(sprintf('%s - %s (%d)', $status->value, $status->label(), count($selected)));
		if ($status === HolidayDiffStatus::UNPARSED) {
			$io->table(['Line', 'Text'], array_map(
				static fn(HolidayDiffRow $row): array => [$row->parsed->lineNumber, $row->parsed->rawLine],
				$selected
			));
			return;
		}
		if ($status === HolidayDiffStatus::MISSING) {
			$io->table(['Line', 'Date', 'Name'], array_map(
				fn(HolidayDiffRow $row): array => [$row->parsed->lineNumber, $this->date($row), $row->parsed->name],
				$selected
			));
			return;
		}
		$io->table(['Line', 'Date', 'Name', 'Stored as', 'Stored date', 'ID', 'Score'], array_map(
			fn(HolidayDiffRow $row): array => [
				$row->parsed->lineNumber,
				$this->date($row),
				$row->parsed->name,
				$row->candidate?->name,
				$this->storedDate($row),
				$row->candidate?->metadataId,
				number_format($row->score, 2),
			],
			$selected
		));
	}

	/**
	 * @param HolidayDiffRow[] $rows
	 * @return HolidayDiffRow[] sorted by date, so a section reads like a calendar
	 */
	private function ofStatus(array $rows, HolidayDiffStatus $status): array
	{
		$selected = array_values(array_filter(
			$rows,
			static fn(HolidayDiffRow $row): bool => $row->status === $status
		));
		usort($selected, static function (HolidayDiffRow $left, HolidayDiffRow $right): int {
			return [$left->parsed->month, $left->parsed->day, $left->parsed->lineNumber]
				<=> [$right->parsed->month, $right->parsed->day, $right->parsed->lineNumber];
		});
		return $selected;
	}

	private function date(HolidayDiffRow $row): string
	{
		if (!$row->parsed->parsed) {
			return '';
		}
		return sprintf('%02d.%02d', $row->parsed->day, $row->parsed->month);
	}

	private function storedDate(HolidayDiffRow $row): string
	{
		if ($row->candidate === null) {
			return '';
		}
		if ($row->candidate->kind === 'floating') {
			return 'floating';
		}
		return sprintf('%02d.%02d', $row->candidate->day, $row->candidate->month);
	}

	/**
	 * @param HolidayDiffRow[] $rows
	 */
	private function toJson(array $rows): string
	{
		$payload = array_map(static fn(HolidayDiffRow $row): array => [
			'line' => $row->parsed->lineNumber,
			'status' => $row->status->value,
			'day' => $row->parsed->day,
			'month' => $row->parsed->month,
			'name' => $row->parsed->name,
			'raw_line' => $row->parsed->rawLine,
			'score' => round($row->score, 3),
			'stored_id' => $row->candidate?->metadataId,
			'stored_kind' => $row->candidate?->kind,
			'stored_name' => $row->candidate?->name,
			'stored_day' => $row->candidate?->day,
			'stored_month' => $row->candidate?->month,
		], $rows);
		return (string)json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	}
}
