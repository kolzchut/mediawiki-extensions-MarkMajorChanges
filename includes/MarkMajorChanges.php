<?php

namespace MediaWiki\Extension\MarkMajorChanges;

use MediaWiki\MediaWikiServices;
use MediaWiki\Storage\NameTableAccessException;

/**
 * Static helper exposing the change-tag names owned by this extension and
 * resolving the "handled" tag id.
 */
class MarkMajorChanges {
	private static string $tagname = 'majorchange';
	private static string $secondarytagname = 'arabic';

	public static function getMainTagName(): string {
		return self::$tagname;
	}

	public static function getSecondaryTagName(): string {
		return self::$secondarytagname;
	}

	/**
	 * Resolve the change-tag definition id for the "handled" tag.
	 *
	 * Called from static contexts (pager/API query building) where constructor
	 * injection is not available, so the service is fetched here.
	 *
	 * @param string $tagName
	 * @return int|null
	 */
	public static function getIdForTag( string $tagName ): ?int {
		$changeTagDefStore = MediaWikiServices::getInstance()->getChangeTagDefStore();
		try {
			return $changeTagDefStore->getId( 'שינוי מהותי טופל' );
		} catch ( NameTableAccessException $exception ) {
			// Tag not yet defined in this wiki.
			return null;
		}
	}

}
