<?php

/**
 * @ingroup SpecialPage Pager
 */
class MajorChangesLogPager extends LogPager {
	private $status;
	private $mode;
	private $startDate;
	private $endDate;
	protected $mConds;


	/**
	 * @param IContextSource $context
	 * @param FormOptions $opts
	 */
	function __construct( LogEventsList $logEventsList, FormOptions $opts ) {
		// Override TagLogFormatter. We don't want to override it system-wide, just here
		global $wgLogActionsHandlers;
		$wgLogActionsHandlers['tag/update'] = 'MajorChangesTagLogFormatter';

		$this->status    = $opts->getValue( 'status' );
		$this->startDate = $opts->getValue( 'start' );
		$this->endDate   = $opts->getValue( 'end' );
		$this->mode      = $opts->getValue( 'mode' );

		parent::__construct(
			$logEventsList,
			[ 'tag' ],
			$opts->getValue( 'user' ),
			$opts->getValue( 'page' ),
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
		$this->limitByDates();

		$info = parent::getQueryInfo();

		switch ( $this->status ) {
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

		switch ( $this->mode ) {
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

	protected function limitByDates() {
		$dbr = wfGetDB( DB_REPLICA );
		if ( $this->startDate ) {
			$this->mConds[] = 'log_timestamp >= ' .
			           $dbr->addQuotes( $dbr->timestamp( new DateTime( $this->startDate ) ) );
		}

		if ( $this->endDate ) {
			// Add 1 day, so we check for "any date before tomorrow"
			$this->mConds[] = 'log_timestamp < ' .
			           $dbr->addQuotes( $dbr->timestamp( new DateTime( $this->endDate . ' +1 day' ) ) );
		}
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
