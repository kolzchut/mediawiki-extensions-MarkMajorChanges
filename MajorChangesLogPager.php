<?php

/**
 * @ingroup SpecialPage Pager
 */
class MajorChangesLogPager extends LogPager {
	/** @var string */
	private $status;
	/** @var string */
	private $mode;
	/** @var string */
	private $startDate;
	/** @var string */
	private $endDate;
	/** @var array */
	protected array $mConds;

	/**
	 * @param LogEventsList $logEventsList
	 * @param FormOptions $opts
	 */
	public function __construct( LogEventsList $logEventsList, FormOptions $opts ) {
		// Override TagLogFormatter. We don't want to override it system-wide, just here
		global $wgLogActionsHandlers;
		$wgLogActionsHandlers['tag/update'] = 'MajorChangesTagLogFormatter';

		$this->status    = $opts->getValue( 'status' );
		$this->startDate = $opts->getValue( 'start' );
		$this->endDate   = $opts->getValue( 'end' );
		$this->mode      = $opts->getValue( 'mode' );

		$this->limitToRelevantTags();
		$this->limitByDates();

		parent::__construct(
			$logEventsList,
			[ 'tag' ],
			$opts->getValue( 'user' ),
			$opts->getValue( 'page' ),
			'',
			$this->mConds,
			false,
			false,
			null
		);
	}

	/** @inheritDoc */
	public function getQueryInfo(): array {
		$info = parent::getQueryInfo();

		$tagId = MarkMajorChanges::getIdForTag( 'שינוי מהותי טופל' );

		switch ( $this->status ) {
			case 'queue':
				$cond = [ 'ct_tag_id IS NULL OR ct_tag_id != ' . $tagId ];
				break;
			case 'done':
				$cond = [ 'ct_tag_id' => $tagId ];
				break;
		}

		if ( isset( $cond ) ) {
			$info[ 'tables' ][] = 'change_tag';
			$info[ 'join_conds' ][ 'change_tag' ] = [ 'LEFT OUTER JOIN', 'ct_log_id=log_id' ];
			$info[ 'conds' ] = array_merge_recursive( $info[ 'conds' ], $cond );
		}

		return $info;
	}

	/**
	 * @return string[]
	 */
	public static function getAllowedModes() {
		return [ 'onlymajor', 'onlyminor', 'all' ];
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

		// We might not have a database in the parent yet, so get one
		$db = $this->getDatabase() ?: wfGetDB( DB_REPLICA );
		$this->mConds[ 'ls_field' ] = 'Tag';
		$this->mConds[]  = 'ls_value IN (' . $db->makeList( $tagList ) . ')';
	}

	/**
	 * @throws Exception
	 */
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

	/**
	 * @return int
	 */
	public function getTotalNumRows(): int {
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
