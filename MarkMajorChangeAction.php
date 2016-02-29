<?php


/**
 * Class MajorChangeAction
 *
 * A lot of this is ripped from SpecialEditTags, which implements the EditTagsAction
 * (which otherwise I could have simply extended, drat)
 */
class MajorChangeAction extends FormAction {
	private $reason;
	private $isSecondaryChange;
	private $requiredRight = 'markmajorchange';

	public function getRequiredRight() {
		return $this->requiredRight;
	}

	public function show() {
		// Additional security checking before parent::show()
		$errors = $this->getTitle()->getUserPermissionsErrors( $this->getRequiredRight(), $this->getUser() );
		if ( count( $errors ) ) {
			throw new PermissionsError( $this->getRequiredRight(), $errors );
		}

		// Use jQuery.plugin.byteLimit to limit "reason" according to DB column (255B)
		$this->getOutput()->addModules( 'mediawiki.action.majorchange' );

		parent::show();
	}

	public function getName() {
		return 'markmajorchange';
	}

	// We don't want a subtitle text here
	protected function getDescription() {
		return '';
	}

	protected function getPageTitle() {
		return
			$this->msg( 'markmajorchange-action-title' )->params( parent::getPageTitle() )->text();
	}

	public function getRestriction() {
		return 'changetags';
	}

	protected function preText() {
		return $this->msg( 'markmajorchange-form-desc' )->text();
	}

	protected function getFormFields() {
		$fields = array();
		if( $this->hasArabicLangLink() ) {
			$fields[ 'isSecondaryChange' ] = array(
				'type'          => 'check',
				'label-message' => 'markmajorchanges-field-issecondary',
				//'cssclass' => 'form-control'
			);
		}
		$fields['reason'] = array(
			'type' => 'textarea',
			'label-message' => 'markmajorchanges-field-reason',
			'label' => 'What changed?',
			'maxlength' => '200',
			'size' => 60,
			'cols' => 60,
			'rows' => 2,
			'required' => true,
			//'cssclass' => 'form-control' // Bootstrap3
		);

		return $fields;
	}

	public function onSubmit( $data ) {
		// The HTMLForm class takes care of basic validation,
		// such as required fields not being empty...
		$this->reason            = $data['reason'];
		$this->isSecondaryChange = $data['isSecondaryChange'];

		return true;
	}

	/* Copied from ChangeTags::updateTagsWithChecks() */
	protected function logTagAdded( $tags, $revId, $user, $reason ) {
		// log it
		$logEntry = new ManualLogEntry( 'tag', 'update' );
		$logEntry->setPerformer( $user );
		$logEntry->setComment( $reason );

		// find the appropriate target page
		if ( $revId ) {
			$rev = Revision::newFromId( $revId );
			if ( $rev ) {
				$logEntry->setTarget( $rev->getTitle() );
			}
		}

		if ( !$logEntry->getTarget() ) {
			// target is required, so we have to set something
			$logEntry->setTarget( SpecialPage::getTitleFor( 'Tags' ) );
		}

		$logParams = array(
			'4::revid' => $revId,
			'6:list:tagsAdded' => $tags,
			'7:number:tagsAddedCount' => count( $tags ),
		);
		$logEntry->setParameters( $logParams );
		$logEntry->setRelations( array( 'Tag' => $tags ) );

		$dbw = wfGetDB( DB_MASTER );
		$logId = $logEntry->insert( $dbw );

		// Only send this to UDP, not RC, similar to patrol events
		$logEntry->publish( $logId, 'udp' );
		//$logEntry->publish( $logId );

	}

	protected function saveTags() {

		$revId = $this->getTitle()->getLatestRevID();
		$reason = $this->reason;
		$user = $this->getUser();

		$tags = array();
		// Is this a major change, or just a secondary change? Mark both for major
		if ( $this->hasArabicLangLink() ) {
			$tags[] = MarkMajorChanges::getSecondaryTagName();
		}
		if( !$this->isSecondaryChange ) {
			$tags[] = MarkMajorChanges::getMainTagName();
		}

		// Should we use DeferredUpdates::addCallableUpdate?
		$status = ChangeTags::addTags( $tags, null, $revId );
		if( $status === true ) {
			$this->logTagAdded( $tags, $revId, $user, $reason );
			//$this->getTitle()->isMajorChange == true;
			return true;
		}

		return false;
	}

	public function onSuccess() {
		$status = $this->saveTags();
		//@todo notify user according to actual status...

		// Let the user know
		$this->getOutput()->setPageTitle( $this->msg( 'actioncomplete' ) );
		$this->getOutput()->wrapWikiMsg( "<div class=\"successbox\">\n$1\n</div>",
			'tags-edit-success' );
	}



	protected function alterForm( HTMLForm &$form ) {
		$form->setDisplayFormat( 'div' );

		// Suppress default submit, so we can add one that is slightly nicer looking
		$form->suppressDefaultSubmit();
		$form->addButton(
			'submit',
			$this->msg( 'htmlform-submit' )->text(),
			null,
			array(
				'class' => 'btn'
			)
		);
	}


	private function hasArabicLangLink() {
		$langLinks = $this->getPageLankLinks();
		return isset( $langLinks['ar'] );
	}




	/**
	 * Get an array of existing interlanguage links, with the language code in the key and the
	 * title in the value.
	 *
	 * Taken from Core's LinksUpdate::getExistingInterlangs() [includes/deferred/LinksUpdate.php]
	 *
	 * @return array
	 */
	private function getPageLankLinks() {
		$dbr = wfGetDB( DB_SLAVE );
		$res = $dbr->select( 'langlinks', array( 'll_lang', 'll_title' ),
			array( 'll_from' => $this->getTitle()->getArticleID() ), __METHOD__ );
		$arr = array();
		foreach ( $res as $row ) {
			$arr[$row->ll_lang] = $row->ll_title;
		}

		return $arr;
	}

	//@todo get existing change tags so no one tries to resubmit
	private function getExistingChangeTags() {
		//$tags = Revision::
	}



}

