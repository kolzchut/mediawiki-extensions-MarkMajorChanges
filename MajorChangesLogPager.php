<?php

/**
 * @ingroup SpecialPage Pager
 */
class MajorChangesLogPager extends LogPager {
	private $mStatusFilter;
	private $mModeFilter;


	function __construct( $mode, $tag, $performer = null, $title = '', $status = null ) {
		// Override TagLogFormatter. We don't want to override it system-wide, just here
		global $wgLogActionsHandlers;
		$wgLogActionsHandlers['tag/update'] = 'MajorChangesTagLogFormatter';

		$this->mStatusFilter = $status;
		$this->mModeFilter = $mode;

		# Create a LogPager item to get the results and a LogEventsList item to format them...
		$loglist = new LogEventsList(
			$this->getContext(),
			null,
			LogEventsList::USE_CHECKBOXES
		);

		parent::__construct(
			$loglist,
			[ 'tag' ],
			$performer,
			$title,
			'',
			[],
			false,
			false,
			null
		);
	}

	public function getQueryInfo() {
		$db = $this->getDatabase();
		$this->limitToRelevantTags();

		$info = parent::getQueryInfo();

		switch ( $this->mStatusFilter ) {
			case 'queue':
				$cond = [ 'ct_tag IS NULL OR ct_tag != ' . $db->addQuotes( 'שינוי מהותי טופל' ) ];
				break;
			case 'done':
				$cond = [ 'ct_tag' => 'שינוי מהותי טופל' ];
				break;
		}

		if ( isset( $cond ) ) {
			$info[ 'tables' ][] = 'change_tag';
			$info[ 'join_conds' ][ 'change_tag' ] = [ 'LEFT OUTER JOIN', 'ct_log_id=log_id' ];
			$info[ 'conds' ] = array_merge( $info[ 'conds' ], $cond );
		}


		return $info;
	}


	protected function limitToRelevantTags() {
		$mainTag   = MarkMajorChanges::getMainTagName();
		$secondTag = MarkMajorChanges::getSecondaryTagName();

		switch ( $this->mModeFilter ) {
			case 'onlymajor':
				$tagList = [ $mainTag ];
				break;
			case 'onlyminor':
				$tagList = [ $secondTag ];
				break;
			default:
				$tagList = [ $mainTag, $secondTag ];
		}

		$this->mConds[ 'ls_field' ] = 'Tag';
		$this->mConds[]  = 'ls_value IN (' . $this->getDatabase()->makeList( $tagList ) . ')';
	}

	public function getTotalNumRows() {
		$db = $this->getDatabase();
		$info = $this->getQueryInfo();

		return $db->selectRowCount(
			$info['tables'],
			'*',
			$info['conds'],
			__METHOD__,
			[],
			$info['join_conds']
		);
	}

}
