<?php


class MarkMajorChanges {
	static private $tagname = 'majorchange';
	static private $secondarytagname = 'arabic';

	public static function getMainTagName() {
		return self::$tagname;
	}

	public static function getSecondaryTagName() {
		return self::$secondarytagname;
	}

}

