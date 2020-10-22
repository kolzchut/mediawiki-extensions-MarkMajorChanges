<?php

class SpecialMajorChangesLog extends SpecialPage {
	/**
	 * @var User
	 */
	protected $mUserFilter;
	/**
	 * @var Title
	 */
	protected $mTitleFilter;
	protected $mRevTagFilter;
	protected $mModeFilter;
	protected $mStartDateFilter;
	protected $mEndDateFilter;
	protected $mAllowedModes = [
		'all',
		'onlymajor',
		'onlyminor'
	];
	protected $mStatusFilter;
	protected $mAllowedStatus = [
		'all',
		'done',
		'queue'
	];

	public function __construct() {
		parent::__construct( 'MajorChangesLog', 'majorchanges-log' );
	}

	protected function getGroupName() {
		return 'changes';
	}

	public function execute( $parameter ) {
		$this->setHeaders();
		$this->outputHeader();
		$this->getOutput()->addModules( 'mediawiki.special.majorchanges' );

		$opts = new FormOptions();
		$opts->add( 'page', '' );
		$opts->add( 'user', '' );
		$opts->add( 'mode', '' );
		$opts->add( 'status', '' );
		$opts->add( 'start', '' );
		$opts->add( 'end', '' );
		$opts->fetchValuesFromRequest( $this->getRequest() );

		// Show the search form.
		$this->searchForm();

		// Show the log itself.
		$this->showList( $opts );
	}

	private function getActionButtons( $formcontents ) {
		if ( !ChangeTags::showTagEditingUI( $this->getUser() ) ) {
			# If the user doesn't have the ability to edit tags, don't bother showing them the button(s).
			return $formcontents;
		}

		$s = Html::openElement(
				'form',
				[ 'method' => 'post', 'action' => wfScript(), 'id' => 'mw-log-deleterevision-submit' ]
			) . "\n";
		$s .= Html::hidden( 'action', 'historysubmit' ) . "\n";
		$s .= Html::hidden( 'type', 'logging' ) . "\n";
		$s .= Html::hidden( 'wpTagList[]', 'שינוי מהותי טופל' ) . "\n";
		$s .= Html::hidden( 'wpEditToken', $this->getUser()->getEditToken() );
		$s .= Html::hidden( 'wpSubmit', '1' ) . "\n";


		$buttons = Html::element(
			'button',
			[
				'type' => 'submit',
				'name' => 'editchangetags',
				'value' => '1',
				'class' => "editchangetags-log-submit mw-log-editchangetags-button"
			],
			$this->msg( 'majorchanges-log-edit-tags' )->text()
		) . "\n";

		$buttons .= ( new ListToggle( $this->getOutput() ) )->getHTML();
		$legend = Html::element( 'legend', null, $this->msg( 'majorchanges-log-markdone' )->text() );
		$buttons = Xml::tags( 'fieldset', null, $legend . $buttons );


		$s .= $buttons . $formcontents . $buttons;
		$s .= Html::closeElement( 'form' );

		return $s;

	}

	function searchForm() {
		$formDescriptor = [
			'user' => [
				'type' => 'user',
				'name' => 'user',
				'label-message' => 'majorchanges-log-user-filter',
			],
			'page' => [
				'type' => 'title',
				'name' => 'page',
				'label-message' => 'majorchanges-log-title-filter',
			],
			'mode' => [
				'type' => 'select',
				'name' => 'mode',
				'options-messages' => $this->getModeFilterOptions(),
				'label-message' => 'majorchanges-log-mode-filter'
			],
			'status' => [
				'type' => 'select',
				'name' => 'status',
				'options-messages' => $this->getStatusFilterOptions(),
				'label-message' => 'majorchanges-log-status-filter'
			],
			'start' => [
				'type' => 'date',
				'name' => 'start',
				'label-message' => 'majorchanges-start-date-filter'
			],
			'end' => [
				'type' => 'date',
				'name' => 'end',
				'label-message' => 'majorchanges-end-date-filter'
			]
		];

		HTMLForm::factory( 'ooui', $formDescriptor, $this->getContext() )
		        ->setWrapperLegendMsg( 'majorchanges-log-filter' )
		        ->setSubmitTextMsg( 'htmlform-submit' )
			    ->setAction( $this->getPageTitle()->getLocalURL() )
		        ->setMethod( 'get' )
		        ->prepareForm()
		        ->displayForm( false );
	}

	protected function getModeFilterOptions() {
		$options = [];

		foreach ( $this->mAllowedModes as $mode ) {
			// majorchanges-log-mode-all, majorchanges-log-mode-onlymajor, majorchanges-log-mode-onlyminor
			$msgName = "majorchanges-log-mode-{$mode}";
			$options[ $msgName ] = $mode;
		}

		return $options;
	}

	protected function getStatusFilterOptions() {
		$options = [];

		foreach ( $this->mAllowedStatus as $status ) {
			// majorchanges-log-status-all, majorchanges-log-status-done, majorchanges-log-status-queue
			$msgName = "majorchanges-log-status-{$status}";
			$options[ $msgName ] = $status;
		}

		return $options;
	}

	/**
	 * Creates the <select> for which tags to show
	 * @return string Formatted HTML
	 */
	protected function getModeFilter() {
		$options = [];

		foreach ( $this->mAllowedModes as $mode ) {
			// majorchanges-log-mode-all, majorchanges-log-mode-onlymajor, majorchanges-log-mode-onlyminor
			$text = $this->msg( "majorchanges-log-mode-{$mode}" )->escaped();

			$options[] = Html::element(
				'option', [
					'value'    => $mode,
					'selected' => ( $mode === $this->mModeFilter ),
				],
				$text
			);
		}

		$ret = '';
		// Wrap options in a <select>
		$ret .= Html::rawElement(
			'select',
			[ 'id' => 'wpModeFilter', 'name' => 'wpModeFilter' ],
			implode( "\n", $options )
		);

		return $ret;
	}

	protected function getStatusFilter() {
		$options = [];

		foreach ( $this->mAllowedStatus as $status ) {
			// majorchanges-log-status-all, majorchanges-log-status-done, majorchanges-log-status-queue
			$text = $this->msg( "majorchanges-log-status-{$status}" )->escaped();

			$options[] = Html::element(
				'option', [
				'value'    => $status,
				'selected' => ( $status === $this->mStatusFilter ),
			],
				$text
			);
		}

		$ret = '';
		// Wrap options in a <select>
		$ret .= Html::rawElement(
			'select',
			[ 'id' => 'wpStatusFilter', 'name' => 'wpStatusFilter' ],
			implode( "\n", $options )
		);

		return $ret;
	}

	private function showList( FormOptions $opts ) {
		$out = $this->getOutput();

		# Create a LogPager item to get the results and a LogEventsList item to format them...
		$loglist = new LogEventsList(
			$this->getContext(),
			null,
			LogEventsList::USE_CHECKBOXES
		);

		$pager = new MajorChangesLogPager(
			$loglist, $opts
		);
		$pager->doQuery();
		$logBody = $pager->getBody();
		if ( $logBody ) {
			$numRecordsMsg = $this->msg( 'majorchanges-log-filter-num-records' )
			                      ->numParams( $pager->getTotalNumRows() );
			$out->addHTML(
				$pager->getNavigationBar() .
				$this->getActionButtons(
					$numRecordsMsg->text() .
					$loglist->beginLogEventsList() .
					$logBody .
					$loglist->endLogEventsList()
				) .
				$pager->getNavigationBar()
			);
		} else {
			$out->addWikiMsg( 'majorchanges-log-noresults' );
		}
	}
}
