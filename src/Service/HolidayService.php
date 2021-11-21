<?php

namespace App\Service;

use App\Entity\Holiday;
use App\Entity\HolidayDay;
use App\Entity\HolidayMetadata;
use App\Entity\Language;
use DOMDocument;
use DOMElement;

class HolidayService {
	private string $directory = 'public/resources';

	private function getFiles(): array {
		$files = [];
		foreach (scandir($this->directory) as $file) {
			if ($file !== '.' && $file !== '..') {
				$files[] = $file;
			}
		}
		return $files;
	}

	private function getFile(Language $language): string|null {
		$files = $this->getFiles();
		foreach ($files as $file) {
			$dom = new DOMDocument();
			$dom->loadXML(file_get_contents($this->directory . "/" . $file));
			$attributes = $dom->childNodes->item(0)->attributes;
			if ($attributes->getNamedItem('lang')->nodeValue === $language->getName()
				&& $attributes->getNamedItem('uni-lang')->nodeValue === $language->getCode()) {
				return $file;
			}
		}
		return null;
	}

	public function getLanguages(): array {
		$files = $this->getFiles();
		$result = [];
		foreach ($files as $file) {
			$dom = new DOMDocument();
			$dom->loadXML(file_get_contents($this->directory . "/" . $file));
			$attributes = $dom->childNodes->item(0)->attributes;
			$result[] = new Language(
				$attributes->getNamedItem('lang')->nodeValue,
				$attributes->getNamedItem('uni-lang')->nodeValue,
				date("Ymd"));
		}
		return $result;
	}

	public function getHolidays(Language $language): array|null {
		$file = $this->getFile($language);
		if ($file === null) {
			return null;
		}
		$dom = new DOMDocument();
		$dom->loadXML(file_get_contents($this->directory . "/" . $file));
		$DOMNodeList = $dom->getElementsByTagName("day");
		$days = [];
		for ($i = 0; $i < $DOMNodeList->length; $i++) {
			/**
			 * @var DOMElement $dayElement
			 */
			$dayElement = $DOMNodeList->item($i);
			$day = $dayElement->attributes->getNamedItem("day")->nodeValue;
			$month = $dayElement->attributes->getNamedItem("month")->nodeValue;
			$children = $dayElement->getElementsByTagName("holiday");
			$holidays = [];
			for ($j = 0; $j < $children->length; $j++) {
				/**
				 * @var DOMElement $element
				 */
				$element = $children->item($j);
				$id = $element->attributes->getNamedItem("id")->nodeValue;
				$name = $element->getElementsByTagName("name")[0]->nodeValue;
				$description = $element->getElementsByTagName("description")[0]->nodeValue;
				$usual = $element->attributes->getNamedItem("usual")->nodeValue;
				$link = $element->getElementsByTagName("link")[0]->nodeValue;
				$metadata = new HolidayMetadata($id, 0, 0, filter_var($usual, FILTER_VALIDATE_BOOLEAN));
				$holidays[] = new Holiday($language, $metadata, $name, $description, $link);
			}
			$days[] = new HolidayDay($day, $month, $holidays);
		}
		return $days;
	}

	public function getHoliday(Language $language, int $id): Holiday|null {
		$file = $this->getFile($language);
		if ($file === null) {
			return null;
		}
		$dom = new DOMDocument();
		$dom->loadXML(file_get_contents($this->directory . "/" . $file));
		$DOMNodeList = $dom->getElementsByTagName("day");
		for ($i = 0; $i < $DOMNodeList->length; $i++) {
			/**
			 * @var DOMElement $dayElement
			 */
			$dayElement = $DOMNodeList->item($i);
			$children = $dayElement->getElementsByTagName("holiday");
			for ($j = 0; $j < $children->length; $j++) {
				/**
				 * @var DOMElement $element
				 */
				$element = $children->item($j);
				if ($id === +$element->attributes->getNamedItem("id")->nodeValue) {
					$name = $element->getElementsByTagName("name")[0]->nodeValue;
					$description = $element->getElementsByTagName("description")[0]->nodeValue;
					$usual = $element->attributes->getNamedItem("usual")->nodeValue;
					$link = $element->getElementsByTagName("link")[0]->nodeValue;
					return new Holiday($language, $name, $description, filter_var($usual, FILTER_VALIDATE_BOOLEAN), $link);
				}
			}
		}
		return null;
	}
}
