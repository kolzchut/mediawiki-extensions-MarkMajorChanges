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

	public function __construct() {
		parent::__construct( 'MajorChangesLog', 'majorchanges-log' );
	}

	protected function getGroupName() {
		return 'changes';
	}

	public function execute( $parameter ) {
		$this->setHeaders();
		$this->loadParameters();

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
			$text = $this->msg( "majorchanges-log-mode-{$mode}" );

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

	function showList() {
		$out = $this->getOutput();

		$pager = new MajorChangesLogPager(
			$this->mModeFilter,
			$this->mRevTagFilter,
			$this->mUserFilter,
			$this->mTitleFilter
		);
		$pager->doQuery();
		$result = $pager->getResult();
		if ( $result && $result->numRows() !== 0 ) {
			$out->addHTML( $pager->getNavigationBar() .
				Xml::tags( 'ul', [ 'class' => 'plainlinks' ], $pager->getBody() ) .
				$pager->getNavigationBar() );
		} else {
			$out->addWikiMsg( 'majorchanges-log-noresults' );
		}
	}
}

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

		$extraConds = $this->limitByMode( $mode );

		parent::__construct(
			$loglist,
			[ 'tag' ],
			$performer,
			$title,
			null,
			$extraConds
		);

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
		if ( $mode === 'onlymajor' ) {
			$tag = MarkMajorChanges::getMainTagName();
		} elseif ( $mode === 'onlyminor' ) {
			$tag = MarkMajorChanges::getSecondaryTagName();
		} else {
			return [];
		}

		return [
			'ls_field' => 'Tag',
			'ls_value' => $tag
		];
	}
}
