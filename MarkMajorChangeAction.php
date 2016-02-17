<?php


/**
 * Class MajorChangeAction
 *
 * A lot of this is ripped from SpecialEditTags, which implements the EditTagsAction
 * (which otherwise I could have simply extended, drat)
 */
class MajorChangeAction extends FormAction {
	private $reason;
	private $onlyArabic;
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

	//@todo use JS plugin jQuery.plugin.byteLimit like mediawiki.action.edit.js
	protected function getFormFields() {
		$fields = array(
			'only-arabic' => array(
				'type' => 'check',
				'label' => 'זה לא שינוי מהותי, אבל רלבנטי לתרגום לערבית',
				//'cssclass' => 'form-control'
			),
			'reason' => array(
				'type' => 'text',
				'label-message' => 'markmajorchanges-reason',
				'label' => 'What changed?',
				'maxlength' => '200',
				'size' => 60,
				'required' => true,
				//'cssclass' => 'form-control' // Bootstrap3

			)
		);

		return $fields;
	}

	public function onSubmit( $data ) {
		// The HTMLForm class takes care of basic validation,
		// such as required fields not being empty...
		$this->reason = $data['reason'];
		$this->onlyArabic = $data['only-arabic'];

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

	}

	protected function saveTags() {
		$revId = $this->getTitle()->getLatestRevID();
		$reason = $this->reason;
		$user = $this->getUser();
		// Is this a major change, or only relevant to Arabic? Change tag accordingly
		$tags = array( $this->onlyArabic ? MarkMajorChanges::getSecondaryTagName() : MarkMajorChanges::getMainTagName() );

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



}

