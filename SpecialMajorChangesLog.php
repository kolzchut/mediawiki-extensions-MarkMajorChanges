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
		$this->loadParameters();

		$this->getOutput()->addModules( 'mediawiki.special.majorchanges' );


		// Show the search form.
		$this->searchForm();

		// Show the log itself.
		$this->showList();
	}

	function loadParameters() {
		$request = $this->getRequest();

		$this->mTitleFilter = trim( $request->getText( 'wpTitleFilter' ) );
		$this->mRevTagFilter = $request->getText( 'wpRevTagFilter' );
		$this->mUserFilter = trim( $request->getText( 'wpUserFilter' ) );
		$this->mModeFilter = trim( $request->getText( 'wpModeFilter' ) );
		$this->mStatusFilter = trim( $request->getText( 'wpStatusFilter' ) );

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
		$output = Html::element( 'legend', null, $this->msg( 'majorchanges-log-filter' )->text() );
		$fields = [];
		// Search conditions

		$fields['majorchanges-log-user-filter'] =
			Html::input( 'wpUserFilter', $this->mUserFilter );

		$fields['majorchanges-log-title-filter'] =
			Html::input( 'wpTitleFilter', $this->mTitleFilter );

		/*
		$fields['majorchanges-log-tag-filter'] =
			Html::input( 'wpRevTagFilter', $this->mRevTagFilter );
		*/

		$fields['majorchanges-log-mode-filter'] = $this->getModeFilter();
		$fields['majorchanges-log-status-filter'] = $this->getStatusFilter();


		$output .= Xml::tags( 'form',
			[ 'method' => 'get', 'action' => $this->getPageTitle()->getLocalURL() ],
			Xml::buildForm( $fields, 'htmlform-submit' ) .
			Html::hidden( 'title', $this->getPageTitle()->getPrefixedDBkey() )
		);
		$output = Xml::tags( 'fieldset', null, $output );
		$this->getOutput()->addHTML( $output );
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

	private function showList() {
		$out = $this->getOutput();
		$loglist = new LogEventsList(
			$this->getContext(),
			null,
			LogEventsList::USE_CHECKBOXES
		);

		$pager = new MajorChangesLogPager(
			$this->mModeFilter,
			$this->mRevTagFilter,
			$this->mUserFilter,
			$this->mTitleFilter,
			$this->mStatusFilter
		);
		$pager->doQuery();
		$logBody = $pager->getBody();
		if ( $logBody ) {
			$out->addHTML(
				$pager->getNavigationBar() .
				$this->getActionButtons(
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
