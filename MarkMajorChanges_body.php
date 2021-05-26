<?php


use MediaWiki\MediaWikiServices;
use MediaWiki\Storage\NameTableAccessException;

class MarkMajorChanges {
	private static $tagname = 'majorchange';
	private static $secondarytagname = 'arabic';

	public static function getMainTagName() {
		return self::$tagname;
	}

	public static function getSecondaryTagName() {
		return self::$secondarytagname;
	}

	public static function getIdForTag( $tagName ) {
		$changeTagDefStore = MediaWikiServices::getInstance()->getChangeTagDefStore();
		try {
			return $changeTagDefStore->getId( 'שינוי מהותי טופל' );
		} catch ( NameTableAccessException $exception ) {
			// Return nothing.
			return null;
		}

	}

}

