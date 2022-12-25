<?php

use MediaWiki\MediaWikiServices;

/**
 * Class MajorChangeAction
 *
 * A lot of this is ripped from SpecialEditTags, which implements the EditTagsAction
 * (which otherwise I could have simply extended, drat)
 */
class MajorChangeAction extends FormAction {
	/** @var ?string */
	private ?string $reason;

	/**
	 * @throws PermissionsError
	 * @throws ErrorPageError
	 */
	public function show() {
		if ( !$this->hasArabicLangLink() ) {
			throw new ErrorPageError(
				'markmajorchanges-not-translated-error', 'markmajorchanges-not-translated-error'
			);
		}

		// Use jQuery.plugin.byteLimit to limit "reason" according to DB column (255B)
		$this->getOutput()->addModules( 'mediawiki.action.majorchange' );

		parent::show();
	}

	/**
	 * Users need both 'markmajorchanges' & 'changetags' permissions, but getRestriction() only
	 * allows to check one permission, so we do another check here
	 *
	 * @param User $user
	 *
	 * @return void
	 * @throws PermissionsError
	 * @throws ReadOnlyError
	 * @throws UserBlockedError
	 */
	protected function checkCanExecute( User $user ) {
		$permissionManager = MediaWikiServices::getInstance()->getPermissionManager();
		$errors = $permissionManager->getPermissionErrors( 'changetags', $this->getUser(), $this->getTitle() );
		if ( count( $errors ) ) {
			throw new PermissionsError( 'changetags', $errors );
		}

		parent::checkCanExecute( $user );
	}

	/** @inheritDoc */
	public function getName(): string {
		return 'markmajorchange';
	}

	/**
	 * We don't want a subtitle text here
	 *
	 * @inheritDoc
	 */
	protected function getDescription(): string {
		return '';
	}

	/** @inheritDoc */
	protected function getPageTitle(): string {
		return $this->msg( 'markmajorchange-action-title' )->params( parent::getPageTitle() )->text();
	}

	/** @inheritDoc */
	public function getRestriction(): string {
		return 'markmajorchange';
	}

	/** @inheritDoc */
	protected function preText(): string {
		return $this->msg( 'markmajorchange-form-desc' )->text();
	}

	/** @inheritDoc */
	protected function getFormFields(): array {
		$fields = [];
		$fields['jira_issue_id'] = [
			'type' => 'text',
			'label-message' => 'markmajorchanges-field-jira-issue'
		];
		$fields['reason'] = [
			'type' => 'multiselect',
			'label-message' => 'markmajorchanges-field-reason',
			'options' => $this->getReasonOptionsArray(),
		];

		$fields['reason-other'] = [
			'type' => 'textarea',
			'label-message' => 'markmajorchanges-field-reason-other',
			'maxlength' => '200',
			'size' => 60,
			'rows' => 2,
		];

		return $fields;
	}

	/**
	 * @return array
	 */
	private function getReasonOptionsArray(): array {
		$reasonsArray = [];

		// Add the "other" option
		// $other = $this->msg( 'markmajorchanges-field-reason-options-other' )->text();
		// $reasonsArray[ $other ] = $other;

		// Now add the rest from the system message
		$reasons = explode( "\n", $this->msg( 'markmajorchanges-field-reason-options' )->text() );
		foreach ( $reasons as $value ) {
			$reasonsArray[ $value ] = $value;
		}

		return $reasonsArray;
	}

	/**
	 * The HTMLForm class takes care of basic validation, such as required fields not being empty...
	 *
	 * @param array $data
	 *
	 * @return bool|Status
	 */
	public function onSubmit( $data ) {
		// Save for later
		$this->reason = $this->getRequest()->getArray( 'wpreason' );
		$this->reason[] = $this->getRequest()->getText( 'wpreason-other' );
		$this->reason = implode( "\n", $this->reason );
		$this->reason = trim( $this->reason );

		// Make sure we got a reason from one of the above fields
		if ( empty( $this->reason ) ) {
			return Status::newFatal( 'markmajorchanges-field-reason-required' );
		}

		// Check if the reported JIRA issue actually exists
		$jiraIssueId = $data['jira_issue_id'];
		$jiraIssueId = !empty( $jiraIssueId ) ? trim( $jiraIssueId ) : $jiraIssueId;
		if ( $jiraIssueId ) {
			$issueRequest = $this->performJiraIssueRequest( $jiraIssueId );
			if ( !$this->jiraIssueExists( $issueRequest ) ) {
				return Status::newFatal( 'markmajorchanges-jira-parent-issue-doesnt-exist', $jiraIssueId );
			} elseif ( !$this->jiraIssueOpen( $issueRequest ) ) {
				return Status::newFatal( 'markmajorchanges-jira-parent-issue-is-closed', $jiraIssueId );
			}
		}

		return true;
	}

	/**
	 * @see Copied from ChangeTags::updateTagsWithChecks()
	 *
	 * @param array|null $tags Tags to add to the change
	 * @param int|null $rev_id The rev_id of the change to add the tags to
	 * @param User|null $user Tagging user
	 * @param string $reason Comment for the log
	 *
	 * @return void
	 * @throws MWException
	 */
	protected function logTagAdded( ?array $tags, ?int $rev_id, ?User $user, string $reason ) {
		// log it
		$logEntry = new ManualLogEntry( 'tag', 'update' );
		$logEntry->setPerformer( $user );
		$logEntry->setComment( $reason );

		// find the appropriate target page
		if ( $rev_id ) {
			$rev = Revision::newFromId( $rev_id );
			if ( $rev ) {
				$logEntry->setTarget( $rev->getTitle() );
			}
		}

		if ( !$logEntry->getTarget() ) {
			// target is required, so we have to set something
			$logEntry->setTarget( SpecialPage::getTitleFor( 'Tags' ) );
		}

		$logParams = [
			'4::revid' => $rev_id,
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

	/**
	 * @return bool
	 * @throws MWException
	 */
	protected function saveTags(): bool {
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

	/**
	 * @inheritDoc
	 * @throws MWException
	 */
	public function onSuccess() {
		$this->saveTags();
		// @todo notify user according to actual status returned by $this->saveTags()
		// Let the user know
		$this->getOutput()->setPageTitle( $this->msg( 'actioncomplete' ) );
		$this->getOutput()->addHTML( Html::successBox( $this->msg( 'tags-edit-success' )->escaped() ) );

		$request = $this->getJiraApiRequestCreateIssue();
		$status = $request->execute();
		if ( count( $status->getErrors() ) > 0 ) {
			$this->getOutput()->addWikiMsg( 'markmajorchanges-jira-error', $request->getContent() );
		}

		$this->getOutput()->addReturnTo( $this->getTitle() );
	}

	/**
	 * @return string|null Jira user's account ID
	 */
	private function lookupCurrentUserJiraAccountId(): ?string {
		$email = $this->getOutput()->getUser()->getEmail();
		$accountId = null;
		if ( !empty( $email ) ) {
			$request = $this->getJiraApiRequest( 'user/search?query=' . $email );
			$request->execute();
			$content = $this->getResponseContent( $request );
			$accountId = $content[0]->accountId;
		}

		return $accountId;
	}

	/**
	 * @param MWHttpRequest $request
	 *
	 * @return mixed
	 */
	private function getResponseContent( MWHttpRequest $request ) {
		return json_decode( $request->getContent() );
	}

	/**
	 * @return MWHttpRequest|null
	 */
	private function getJiraApiRequestCreateIssue(): ?MWHttpRequest {
		return $this->getJiraApiRequest( 'issue', $this->getJiraCreateIssueFields() );
	}

	/**
	 * @param string $issueKey
	 *
	 * @return MWHttpRequest|null
	 */
	private function performJiraIssueRequest( string $issueKey ): ?MWHttpRequest {
		$request = $this->getJiraApiRequest( "issue/$issueKey" );
		$request->execute();
		return $request;
	}

	/**
	 * @param int|MWHttpRequest $issueKeyOrRequest
	 *
	 * @return bool
	 */
	private function jiraIssueExists( $issueKeyOrRequest ): bool {
		if ( is_int( $issueKeyOrRequest ) ) {
			$issueKeyOrRequest = $this->performJiraIssueRequest( $issueKeyOrRequest );
		}

		return ( $issueKeyOrRequest->getStatus() < 400 );
	}

	/**
	 * @param int|MWHttpRequest $issueKeyOrRequest
	 *
	 * @return bool
	 */
	private function jiraIssueOpen( $issueKeyOrRequest ): bool {
		if ( is_int( $issueKeyOrRequest ) ) {
			$issueKeyOrRequest = $this->performJiraIssueRequest( $issueKeyOrRequest );
		}

		$content = $issueKeyOrRequest->getContent();
		return ( json_decode( $content )->fields->resolution === null );
	}

	/**
	 * @param string $urlPath
	 * @param array $postData
	 *
	 * @return MWHttpRequest
	 */
	private function getJiraApiRequest( string $urlPath, array $postData = [] ): ?MWHttpRequest {
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

	/**
	 * @return array[]
	 */
	private function getJiraCreateIssueFields(): array {
		$jiraConf = MediaWikiServices::getInstance()->getMainConfig()->get( 'MarkMajorChangesJiraConf' );

		$parentIssueId = $this->getRequest()->getText( 'wpjira_issue_id' );

		$fields = [
			'project' => [
				'key' => $jiraConf['project'],
			],
			'summary' => $this->getTitle()->getFullText(),
			'description' => $this->reason,
			'issuetype' => [
				// 10009 => 'שינוי מהותי', 10001 => 'משימת משנה'
				'id' => $parentIssueId ? '10001' : '10009'
			],
			'reporter' => [
				'accountId' => $this->lookupCurrentUserJiraAccountId()
			],
			// customfield_11689 "Page Title"
			'customfield_10201' => $this->getTitle()->getFullText(),
			// customfield_11689 "Link"
			'customfield_11689' => $this->getShortUrl(),
			// customfield_10800 "WikiPage Categories"
			'customfield_10800' => $this->getPageCategories()
		];

		if ( ExtensionRegistry::getInstance()->isLoaded( 'ArticleContentArea' ) ) {
			$contentArea = \MediaWiki\Extension\ArticleContentArea\ArticleContentArea::getArticleContentArea(
				$this->getTitle()
			);
			// customfield_11691 "content_area"
			$fields['customfield_11691'] = $contentArea;
		}

		if ( !empty( $parentIssueId ) ) {
			$fields['parent']['key'] = $parentIssueId;
		}

		return [ 'fields' => $fields ];
	}

	/**
	 * @return bool
	 */
	private function hasArabicLangLink(): bool {
		$langLinks = $this->getPageLankLinks();
		return isset( $langLinks['ar'] );
	}

	/**
	 * @return string a short URL for Jira's tiny URL field
	 */
	private function getShortUrl(): ?string {
		$jiraConf = MediaWikiServices::getInstance()->getMainConfig()->get( 'MarkMajorChangesJiraConf' );
		$shortlinkFormat = $jiraConf['shortlinkFormat'];
		$articleId = $this->getTitle()->getArticleID();
		$lang = $this->getLanguage()->getHtmlCode();

		return $shortlinkFormat ?
			str_replace( [ '$articleId', '$lang' ], [ $articleId, $lang ], $shortlinkFormat ) : null;
	}

	/**
	 * @return array
	 */
	private function getPageCategories(): array {
		$categories = array_keys( $this->getTitle()->getParentCategories() );
		$categories = str_replace( $this->getLanguage()->getNsText( NS_CATEGORY ) . ':', '', $categories );

		return $categories;
	}

	/**
	 * Get an array of existing interlanguage links, with the language code in the key and the
	 * title in the value.
	 *
	 * Taken from Core's LinksUpdate::getExistingInterlangs() [includes/deferred/LinksUpdate.php]
	 *
	 * @return array
	 */
	private function getPageLankLinks(): array {
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
	protected function usesOOUI(): bool {
		return true;
	}

	// @todo get existing change tags so no one tries to resubmit
	/*
	private function getExistingChangeTags() {
		// $tags = Revision::
	}
	*/

}
