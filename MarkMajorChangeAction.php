<?php

use MediaWiki\MediaWikiServices;


/**
 * Class MajorChangeAction
 *
 * A lot of this is ripped from SpecialEditTags, which implements the EditTagsAction
 * (which otherwise I could have simply extended, drat)
 */
class MajorChangeAction extends FormAction {
	private $reason;

	/**
	 * @throws PermissionsError
	 * @throws ErrorPageError
	 */
	public function show() {
		if ( !$this->hasArabicLangLink() ) {
			throw new ErrorPageError( 'markmajorchanges-not-translated-error', 'markmajorchanges-not-translated-error' );
		}

		// Use jQuery.plugin.byteLimit to limit "reason" according to DB column (255B)
		$this->getOutput()->addModules( 'mediawiki.action.majorchange' );

		parent::show();
	}

	// Users need both 'markmajorchanges' & 'changetags' permissions, but getRestriction() only
	// allows to check one permission, so we do another check here
	protected function checkCanExecute( User $user ) {
		$permissionManager = MediaWikiServices::getInstance()->getPermissionManager();
		$errors = $permissionManager->getPermissionErrors( 'changetags', $this->getUser(), $this->getTitle() );
		if ( count( $errors ) ) {
			throw new PermissionsError( 'changetags', $errors );
		}

		parent::checkCanExecute( $user );
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
		return 'markmajorchange';
	}

	protected function preText() {
		return $this->msg( 'markmajorchange-form-desc' )->text();
	}

	protected function getFormFields() {
		$fields = [];
		$fields['jira_issue_id'] = [
			'type' => 'text',
			'label-message' => 'markmajorchanges-field-jira-issue'
		];
		$fields['reason'] = [
			'type' => 'textarea',
			'label-message' => 'markmajorchanges-field-reason',
			'maxlength' => '200',
			'size' => 60,
			'rows' => 2,
			'required' => true,
			// 'cssclass' => 'form-control' // Bootstrap3
		];

		return $fields;
	}

	public function onSubmit( $data ) {
		// The HTMLForm class takes care of basic validation,
		// such as required fields not being empty...
		$this->reason = $data['reason'];

		if( !empty( $data['jira_issue_id'] ) && !$this->jiraIssueExists( $data['jira_issue_id'] ) ) {
			return Status::newFatal( 'markmajorchanges-jira-parent-issue-doesnt-exist', $data['jira_issue_id'] );
		}

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

		$logParams = [
			'4::revid' => $revId,
			'6:list:tagsAdded' => $tags,
			'7:number:tagsAddedCount' => count( $tags ),
		];
		$logEntry->setParameters( $logParams );
		$logEntry->setRelations( [ 'Tag' => $tags ] );

		$dbw = wfGetDB( DB_PRIMARY );
		$logId = $logEntry->insert( $dbw );

		// Only send this to UDP, not RC, similar to patrol events
		$logEntry->publish( $logId, 'udp' );
	}

	protected function saveTags() {

		$revId = $this->getTitle()->getLatestRevID();
		$reason = $this->reason;
		$user = $this->getUser();

		$tags[] = MarkMajorChanges::getMainTagName();

		// Should we use DeferredUpdates::addCallableUpdate?
		$status = ChangeTags::addTags( $tags, null, $revId );
		if ( $status === true ) {
			$this->logTagAdded( $tags, $revId, $user, $reason );
			return true;
		}

		return false;
	}

	public function onSuccess() {
		$jiraConf = MediaWikiServices::getInstance()->getMainConfig()->get( 'MarkMajorChangesJiraConf' );

		$status = $this->saveTags();
		// Let the user know
		// @todo notify user according to actual status...
		$this->getOutput()->setPageTitle( $this->msg( 'actioncomplete' ) );
		$this->getOutput()->addHTML( Html::successBox( $this->msg('tags-edit-success' )->escaped() ) );

		$request = $this->getJiraApiRequestCreateIssue();
		$status = $request->execute();
		if ( count( $status->getErrors() ) > 0 ) {
			$responseContent = json_decode( $request->getContent() );
			$this->getOutput()->addWikiMsg( 'markmajorchanges-jira-error',
				json_encode( $this->getResponseContent( $request )->errors )
			);
		}

		$this->getOutput()->addReturnTo( $this->getTitle() );
	}

	private function lookupCurrentUserJiraAccountId() {
		$email = $this->getOutput()->getUser()->getEmail();
		$accountId = null;
		if ( !empty ( $email ) ) {
			$request = $this->getJiraApiRequest( 'user/search?query=' . $email );
			$request->execute();
			$content = $this->getResponseContent( $request );
			$accountId = $content[0]->accountId;
		}

		return $accountId;
	}

	private function getResponseContent( $request ) {
		return json_decode( $request->getContent() );
	}

	private function getJiraApiRequestCreateIssue() {
		return $this->getJiraApiRequest( 'issue', $this->getJiraCreateIssueFields() );
	}

	private function jiraIssueExists( $issueKey ) {
		$request = $this->getJiraApiRequest( "issue/$issueKey" );
		$request->execute();
		return ( $request->getStatus() < 400 );
	}

	private function getJiraApiRequest( $urlPath, $postData = [] ) {
		$request = null;
		$jiraConf = MediaWikiServices::getInstance()->getMainConfig()->get( 'MarkMajorChangesJiraConf' );
		if ( isset( $jiraConf['password'] ) ) {
			$requestFactory = MediaWikiServices::getInstance()->getHttpRequestFactory();
			$request = $requestFactory->create( $jiraConf['url'] . '/rest/api/2/' . $urlPath, [
				'method' => empty( $postData ) ? 'GET' : 'POST',
				'username' => $jiraConf['username'],
				'password' => $jiraConf['password'],
				'postData' => json_encode( $postData )
			] );
			$request->setHeader( 'Content-Type', 'application/json' );
			$request->setHeader( 'Accept', 'application/json' );
		}

		return $request;
	}

	private function getJiraCreateIssueFields() {
		$jiraConf = MediaWikiServices::getInstance()->getMainConfig()->get( 'MarkMajorChangesJiraConf' );

		$parentIssueId = $this->getRequest()->getText( 'wpjira_issue_id' );
		$fields = [
			'project' => array(
				'key' => $jiraConf['project'],
			),
			'description' => $this->getRequest()->getText( 'wpreason' ),
			'summary' => $this->getTitle()->getFullText(),
			'issuetype' => [
				'id' => $parentIssueId ? '10001' : '10009'   // 10009 => 'שינוי מהותי', 10001 => 'משימת משנה'
			],
			'reporter' => [
				'accountId' => $this->lookupCurrentUserJiraAccountId()
			],
			'customfield_10201' => $this->getTitle()->getFullText(), // "Page Title"
			'customfield_11689' => $this->getShortUrl(), // customfield_11689 "Link"
		];

		if ( ExtensionRegistry::getInstance()->isLoaded ( 'ArticleContentArea' ) ) {
			$contentArea = \MediaWiki\Extension\ArticleContentArea\ArticleContentArea::getArticleContentArea( $this->getTitle() );
			$fields['customfield_11691'] = $contentArea; // customfield_11691 "content_area"
		}

		if( !empty( $parentIssueId ) ) {
			$fields['parent']['key'] = $parentIssueId;
        }

		return [ 'fields' => $fields ];
	}

	private function hasArabicLangLink() {
		$langLinks = $this->getPageLankLinks();
		return isset( $langLinks['ar'] );
	}

	/**
	 * @return string Shortlink
	 */
	private function getShortUrl() {
		$jiraConf = MediaWikiServices::getInstance()->getMainConfig()->get( 'MarkMajorChangesJiraConf' );
		$shortlinkFormat = $jiraConf['shortlinkFormat'];
		$articleId = $this->getTitle()->getArticleID();
		$lang = $this->getLanguage()->getHtmlCode();

		return $shortlinkFormat ? str_replace( [ '$articleId', '$lang' ], [ $articleId, $lang ], $shortlinkFormat ) : null;
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
		$dbr = wfGetDB( DB_REPLICA );
		$res = $dbr->select(
			'langlinks', [ 'll_lang', 'll_title' ],
			[ 'll_from' => $this->getTitle()->getArticleID() ], __METHOD__
		);
		$arr = [];
		foreach ( $res as $row ) {
			$arr[$row->ll_lang] = $row->ll_title;
		}

		return $arr;
	}

	/**
	 * @return true
	 */
	protected function usesOOUI() {
		return true;
	}

	// @todo get existing change tags so no one tries to resubmit
	private function getExistingChangeTags() {
		// $tags = Revision::
	}



}

