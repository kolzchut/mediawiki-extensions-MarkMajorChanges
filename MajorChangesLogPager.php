<?php

/**
 * @ingroup SpecialPage Pager
 */
class MajorChangesLogPager extends LogPager {
	function __construct( $mode, $tag, $performer = null, $title = '' ) {
		# Create a LogPager item to get the results and a LogEventsList item to format them...
		$loglist = new LogEventsList(
			$this->getContext(),
			null,
			0
		);

		/*
		    $extraConds = $this->limitRevTag( $tag );
			$extraConds = array_merge( $extraConds, $this->limitByMode( $mode ) );
		*/

		parent::__construct(
			$loglist,
			[ 'tag' ],
			$performer,
			$title,
			null
		);

		$this->limitByMode( $mode );


	}

	protected function limitRevTag( $tag ) {
		if ( empty( $tag ) ) {
			return [];
		}
		return [
			'ls_field' => 'Tag',
			'ls_value' => $tag
		];
	}

	protected function limitByMode( $mode ) {
		$dbr = $this->getDatabase();
		$mainTag = MarkMajorChanges::getMainTagName();
		$secondTag =  MarkMajorChanges::getSecondaryTagName();

		switch ( $mode ) {
			case 'onlymajor':
				$tagList = [ $mainTag ];
				break;
			case 'onlyminor':
				$tagList = [ $secondTag ];
				break;
			default:
				$tagList = [ $mainTag, $secondTag ];
		}

		$this->mConds['ls_field'] = 'Tag';
		$this->mConds[] = 'ls_value IN (' . $dbr->makeList( $tagList ) . ')';
	}
}
