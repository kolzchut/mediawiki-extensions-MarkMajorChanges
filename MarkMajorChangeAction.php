<?php

use MediaWiki\MediaWikiServices;

/**
 * Class MajorChangeAction
 *
 * A lot of this is ripped from SpecialEditTags, which implements the EditTagsAction
 * (which otherwise I could have simply extended, drat)
 */
class MajorChangeAction extends FormAction {
	/** @var array Mapping of Jira custom field names to their IDs */
	protected const FIELD_IDS = [
		'LANGUAGE' => 'customfield_10305',
		'PAGE_TITLE' => 'customfield_10201',
		'LINK' => 'customfield_11689',
		'WIKI_CATEGORIES' => 'customfield_10800',
		'ARTICLE_TRANSLATED_TO' => 'customfield_11711',
		'BENEFITS_ENGINE_ID' => 'customfield_11710',
		'CONTENT_AREA' => 'customfield_11691'
	];

	// Constants for issue types
	/** Issue type ID for standalone major change issues (used when no parent ID is provided) */
	private const ISSUE_TYPE_MAJOR_CHANGE = '10009';

	/** Issue type ID for subtasks (used when a parent ID is provided) */
	private const ISSUE_TYPE_SUBTASK = '10001';

	/** @var string|array|null */
	private $reason;
	/** @var array|null */
	private ?array $langLinks;

	/**
	 * Creates Jira issue(s) based on whether a parent issue ID is provided
	 *
	 * If a parent ID is provided:
	 *   - Creates a single subtask under that parent using wiki's content language
	 * If no parent ID is provided:
	 *   - For pages in exempt namespaces: Creates standalone major change issues for all configured languages
	 *   - For other pages: Creates standalone major change issues for each allowed language link
	 *
	 * @param string|null $parentIssueId Parent issue ID if creating a subtask
	 * @return array Array of created issue keys or error messages, keyed by language code
	 */
	protected function createJiraIssues( ?string $parentIssueId ): array {
		$results = [];
		$allowedLanguages = MediaWikiServices::getInstance()->getMainConfig()->get( 'MarkMajorChangesLanguages' );

		if ( $parentIssueId ) {
			// Create a subtask under the specified parent issue using content language
			$langCode = MediaWikiServices::getInstance()->getContentLanguage()->getCode();
			$request = $this->getJiraApiRequestCreateIssue( $parentIssueId, $langCode );
			$status = $request->execute();
			$results[$langCode] = $this->handleJiraResponse( $status, $request );
		} elseif ( $this->isNamespaceExemptFromLangLinks() ) {
			// For exempt namespaces, always create issues for all allowed languages
			// regardless of whether the page has language links
			foreach ( $allowedLanguages as $langCode ) {
				$request = $this->getJiraApiRequestCreateIssue( null, $langCode );
				$status = $request->execute();
				$results[$langCode] = $this->handleJiraResponse( $status, $request );
			}
		} else {
			// For non-exempt namespaces, create issues only for existing allowed language links
			$pageLangLinks = $this->getPageLankLinks();

			foreach ( $pageLangLinks as $langCode => $title ) {
				$request = $this->getJiraApiRequestCreateIssue( null, $langCode );
				$status = $request->execute();
				$results[$langCode] = $this->handleJiraResponse( $status, $request );
			}
		}

		return $results;
	}

	/**
	 * @param Status $status
	 * @param MWHttpRequest $request
	 * @return array
	 */
	protected function handleJiraResponse( $status, $request ): array {
		if ( count( $status->getErrors() ) > 0 ) {
			return [
				'status' => 'error',
				'message' => $request->getContent()
			];
		}
		return [
			'status' => 'success',
			'key' => json_decode( $request->getContent() )->key
		];
	}

	/**
	 * Creates the fields for a Jira issue
	 *
	 * @param string|null $parentIssueId If provided, creates a subtask linked to this parent
	 * @param string|null $langCode Language code for setting the language field
	 * @return array Jira issue fields
	 */
	private function getJiraCreateIssueFields( ?string $parentIssueId = null, ?string $langCode = null ): array {
		$jiraConf = MediaWikiServices::getInstance()->getMainConfig()->get( 'MarkMajorChangesJiraConf' );

		$fields = [
			'project' => [
				'key' => $jiraConf['project'],
			],
			'summary' => $this->getTitle()->getFullText(),
			'description' => $this->reason,
			'issuetype' => [
				'id' => $parentIssueId ? self::ISSUE_TYPE_SUBTASK : self::ISSUE_TYPE_MAJOR_CHANGE
			],
			self::FIELD_IDS['PAGE_TITLE'] => $this->getTitle()->getFullText(),
			self::FIELD_IDS['LINK'] => $this->getShortUrl(),
			self::FIELD_IDS['WIKI_CATEGORIES'] => $this->getPageCategories(),
			self::FIELD_IDS['ARTICLE_TRANSLATED_TO'] => $this->getTranslationLanguagesForJira(),
			self::FIELD_IDS['BENEFITS_ENGINE_ID'] => $this->getBenefitsEngineId()
		];

		// Only set the reporter if the current user maps to a Jira account;
		// sending a null id would be rejected by Jira.
		$reporterId = $this->lookupCurrentUserJiraAccountId();
		if ( $reporterId ) {
			$fields['reporter'] = [ 'id' => $reporterId ];
		}

		// Set language field based on language code
		if ( $langCode ) {
			$languageNameUtils = MediaWikiServices::getInstance()->getLanguageNameUtils();
			$languageName = $languageNameUtils->getLanguageName( $langCode, 'en' );
			if ( $languageName ) {
				$fields[self::FIELD_IDS['LANGUAGE']] = [ 'value' => $languageName ];
			}
		}

		if ( ExtensionRegistry::getInstance()->isLoaded( 'ArticleContentArea' ) ) {
			$contentArea = \MediaWiki\Extension\ArticleContentArea\ArticleContentArea::getArticleContentArea(
				$this->getTitle()
			);
			$fields[self::FIELD_IDS['CONTENT_AREA']] = $contentArea;
		}

		if ( $parentIssueId ) {
			$fields['parent'] = [ 'key' => $parentIssueId ];
		}

		return [ 'fields' => $fields ];
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
	 * @throws PermissionsError
	 * @throws ErrorPageError
	 */
	public function show() {
		// Check if language links are required for this namespace
		if ( !$this->isNamespaceExemptFromLangLinks() && !$this->hasLangLinks() ) {
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

	/**
	 * @inheritDoc
	 * @throws MWException
	 */
	public function onSuccess() {
		$this->saveTags();
		// @todo notify user according to actual status returned by $this->saveTags()
		$this->getOutput()->setPageTitleMsg( $this->msg( 'actioncomplete' ) );
		$this->getOutput()->addHTML( Html::successBox( $this->msg( 'tags-edit-success' )->escaped() ) );

		$parentIssueId = $this->getRequest()->getText( 'wpjira_issue_id' );
		$results = $this->createJiraIssues( $parentIssueId );

		// Show error messages for any failed issues. The change tag was saved,
		// but a Jira issue could not be created — surface that as an error box
		// rather than plain text so the editor is not misled by the success page.
		foreach ( $results as $langCode => $result ) {
			if ( $result['status'] === 'error' ) {
				$this->getOutput()->addHTML( Html::errorBox(
					$this->msg( 'markmajorchanges-jira-error', $result['message'], $langCode )->parse()
				) );
			}
		}

		$this->getOutput()->addReturnTo( $this->getTitle() );
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
	 * Creates a request object for creating a Jira issue
	 *
	 * @param string|null $parentIssueId If provided, creates a subtask linked to this parent
	 * @param string|null $langCode Language code for setting the language field
	 * @return MWHttpRequest|null
	 */
	protected function getJiraApiRequestCreateIssue(
		?string $parentIssueId = null, ?string $langCode = null ): ?MWHttpRequest {
		return $this->getJiraApiRequest( 'issue', $this->getJiraCreateIssueFields( $parentIssueId, $langCode ) );
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
	 * @return array
	 */
	private function getTranslationLanguagesForJira(): array {
		// To update a multi-select field by value and not id, we have to pass an
		// object with specific 'value' => $value
		$translations = [];
		foreach ( $this->getPageLankLinks() as $key => $val ) {
			$translations[] = [ 'value' => $key ];
		}

		return $translations;
	}

	/**
	 * @return string
	 */
	private function getCurrentContentLanguageName(): string {
		$languageNameUtils = MediaWikiServices::getInstance()->getLanguageNameUtils();
		$contentLanguage = MediaWikiServices::getInstance()->getContentLanguage();

		return $languageNameUtils->getLanguageName( $contentLanguage->getCode(), 'en' );
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
			if ( is_array( $content ) && isset( $content[0]->accountId ) ) {
				$accountId = $content[0]->accountId;
			}
		}

		return $accountId;
	}

	/**
	 * @return bool
	 */
	protected function hasLangLinks(): bool {
		return !empty( $this->getPageLankLinks() );
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
	 * Checks if the current namespace is exempt from language links requirement
	 *
	 * @return bool True if the namespace is exempt
	 */
	protected function isNamespaceExemptFromLangLinks(): bool {
		$namespaceId = $this->getTitle()->getNamespace();
		$exemptNamespaces = MediaWikiServices::getInstance()->getMainConfig()->get(
			'MarkMajorChangesLangLinksExemptNamespaces' );

		return in_array( $namespaceId, $exemptNamespaces );
	}

	/**
	 * Get an array of existing interlanguage links, with the language code in the key and the
	 * title in the value.
	 *
	 * Taken from Core's LinksUpdate::getExistingInterlangs() [includes/deferred/LinksUpdate.php]
	 *
	 * @return array
	 */
	protected function getPageLankLinks(): array {
		if ( isset( $this->langLinks ) ) {
			return $this->langLinks;
		}

		$allowedLanguages = MediaWikiServices::getInstance()->getMainConfig()->get( 'MarkMajorChangesLanguages' );

		$dbr = MediaWikiServices::getInstance()
			->getConnectionProvider()
			->getReplicaDatabase();
		$res = $dbr->select(
			'langlinks', [ 'll_lang', 'll_title' ],
			[ 'll_from' => $this->getTitle()->getArticleID() ], __METHOD__
		);
		$arr = [];
		foreach ( $res as $row ) {
			if ( in_array( $row->ll_lang, $allowedLanguages ) ) {
				$arr[$row->ll_lang] = $row->ll_title;
			}
		}

		$this->langLinks = $arr;
		return $arr;
	}

	/**
	 * Get the Government Benefit Engine ID saved by Extension:Cargo on the wiki
	 *
	 * @return null|string
	 */
	private function getBenefitsEngineId() {
		if ( !ExtensionRegistry::getInstance()->isLoaded( 'Cargo' ) ) {
			return null;
		}
		try {
			$cargoQuery = CargoSQLQuery::newFromValues(
				'page_metadata',
				'benefits_engine_id=id',
				'_pageID = ' . $this->getTitle()->getArticleID(),
				null, null, null, null, '1', null
			);
			$result = $cargoQuery->run();
			return empty( $result ) ? null : $result[0]['id'];
		} catch ( MWException $e ) {
			\MWExceptionHandler::logException( $e );
			return null;
		}
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
			$revisionLookup = MediaWikiServices::getInstance()->getRevisionLookup();
			$rev = $revisionLookup->getRevisionById( $rev_id );
			if ( $rev ) {
				$logEntry->setTarget( $rev->getPageAsLinkTarget() );
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

		$dbw = MediaWikiServices::getInstance()
			->getConnectionProvider()
			->getPrimaryDatabase();
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
	protected function getPageTitle() {
		// Return a Message, not a string: a string return from
		// Action::getPageTitle() is deprecated since MediaWiki 1.41.
		return $this->msg( 'markmajorchange-action-title' )->params( parent::getPageTitle() );
	}

	/** @inheritDoc */
	public function getRestriction(): string {
		return 'markmajorchange';
	}

	/** @inheritDoc */
	protected function preText(): string {
		return $this->msg( 'markmajorchange-form-desc' )->text();
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
