<?php

use MediaWiki\MediaWikiServices;
use MediaWiki\Storage\NameTableAccessException;

class MarkMajorChanges {
	/** @var string */
	private static string $tagname = 'majorchange';
	/** @var string */
	private static string $secondarytagname = 'arabic';

	/**
	 * @return string
	 */
	public static function getMainTagName() {
		return self::$tagname;
	}

	/**
	 * @return string
	 */
	public static function getSecondaryTagName() {
		return self::$secondarytagname;
	}

	/**
	 * @param string $tagName
	 *
	 * @return int|null
	 */
	public static function getIdForTag( string $tagName ) {
		$changeTagDefStore = MediaWikiServices::getInstance()->getChangeTagDefStore();
		try {
			return $changeTagDefStore->getId( 'שינוי מהותי טופל' );
		} catch ( NameTableAccessException $exception ) {
			// Return nothing.
			return null;
		}
	}

}
