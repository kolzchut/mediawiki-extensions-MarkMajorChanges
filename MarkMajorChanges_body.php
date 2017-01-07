<?php


class MarkMajorChanges {
	private static $tagname = 'majorchange';
	private static $secondarytagname = 'arabic';

	public static function getMainTagName() {
		return self::$tagname;
	}

	public static function getSecondaryTagName() {
		return self::$secondarytagname;
	}

}

