<?php

namespace App\Service;

use App\Entity\Holiday;
use App\Entity\HolidayMetadata;
use App\Entity\Language;
use App\Repository\MetadataRepository;
use Doctrine\ORM\EntityManagerInterface;

class HolidayService {
	private string $directory = 'public/resources';
	private EntityManagerInterface $entityManager;
	private MetadataRepository $metadataRepository;

	public function __construct(EntityManagerInterface $entityManager, MetadataRepository $metadataRepository) {
		$this->entityManager = $entityManager;
		$this->metadataRepository = $metadataRepository;
	}

	public function migrate(Language $language): void {
		$file = $this->directory . '/' . $language->getCode() . '.json';
		$content = file_get_contents($file);
		$data = json_decode($content);
		foreach ($data as $datum) {
			$day = $datum->day;
			$month = $datum->month;
			$holidays = $datum->holidays;
			foreach ($holidays as $holiday) {
				$id = $holiday->id;
				$name = $holiday->name;
				$description = $holiday->description;
				$usual = $holiday->usual;
				$link = $holiday->link;
				$metadata = $this->metadataRepository->findOneBy(['id' => $id]);
				if ($metadata == null) {
					$metadata = new HolidayMetadata($id, $month, $day, $usual);
					$this->entityManager->persist($metadata);
				}
				$holidayDTO = new Holiday($language, $metadata, $name, $description, $link);
				$metadata->addHoliday($holidayDTO);
				$this->entityManager->persist($holidayDTO);
				$this->entityManager->flush();
			}
		}
	}
}
