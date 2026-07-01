<?php
/**
 * Query action to List the Major Changes log events, with optional filtering by various parameters.
 * This extends the regular ApiQueryLogEvents to add some filtering options.
 *
 * @ingroup API
 */

namespace MediaWiki\Extension\MarkMajorChanges;

use LogFormatterFactory;
use MediaWiki\Api\ApiBase;
use MediaWiki\Api\ApiQuery;
use MediaWiki\Api\ApiQueryLogEvents;
use MediaWiki\CommentFormatter\RowCommentFormatter;
use MediaWiki\CommentStore\CommentStore;
use MediaWiki\Storage\NameTableStore;
use MediaWiki\Title\TitleFactory;
use MediaWiki\User\UserNameUtils;

class ApiQueryMajorChangesLogEvents extends ApiQueryLogEvents {

	private TitleFactory $titleFactory;

	/**
	 * The service arguments mirror ApiQueryLogEvents so we can forward them to
	 * the parent constructor; TitleFactory is our own addition.
	 *
	 * @param ApiQuery $query
	 * @param string $moduleName
	 * @param CommentStore $commentStore
	 * @param RowCommentFormatter $commentFormatter
	 * @param NameTableStore $changeTagDefStore
	 * @param UserNameUtils $userNameUtils
	 * @param LogFormatterFactory $logFormatterFactory
	 * @param TitleFactory $titleFactory
	 */
	public function __construct(
		ApiQuery $query,
		string $moduleName,
		CommentStore $commentStore,
		RowCommentFormatter $commentFormatter,
		NameTableStore $changeTagDefStore,
		UserNameUtils $userNameUtils,
		LogFormatterFactory $logFormatterFactory,
		TitleFactory $titleFactory
	) {
		parent::__construct(
			$query,
			$moduleName,
			$commentStore,
			$commentFormatter,
			$changeTagDefStore,
			$userNameUtils,
			$logFormatterFactory
		);
		$this->titleFactory = $titleFactory;
	}

	/** @inheritDoc */
	public function execute() {
		// Always force 'tag/update' as the action. This also prevents someone from using this to
		// bypass the regular log protections
		$this->getRequest()->setVal( 'leaction', 'tag/update' );
		$params = $this->extractRequestParams();
		$this->limitToRelevantTags( $params['mode'] );
		$this->limitToCategory( $params['category'] );

		parent::execute();

		// We want to give our consumers URLs right in the results, without further processing
		// Since we can't override extractRowInfo() we have to iterate over it all
		// Get the result data, remove it from the ApiResult object, modify it and push it back in
		$prop = array_flip( $params['prop'] );
		$result = $this->getResult();
		$resultData = $this->getResult()->getResultData( [ 'query', $this->getModuleName() ] );
		$result->reset();
		$rows = $resultData ?: [];
		foreach ( $rows as &$row ) {
			if ( is_array( $row ) ) {
				if ( isset( $row[ 'pageid' ] ) && !empty( $row[ 'pageid' ] ) && isset( $prop[ 'url' ] ) ) {
					$title = $this->titleFactory->newFromID( $row[ 'pageid' ] );
					$row[ 'url' ] = $title ? $title->getFullURL() : '';
				}
				unset( $row[ 'type' ], $row[ 'action' ] );
				$result->addValue( [ 'query', $this->getModuleName() ], null, $row );
			}
		}
	}

	/**
	 * @param string|null $category
	 *
	 * @return void
	 * @throws \MediaWiki\Api\ApiUsageException
	 */
	protected function limitToCategory( ?string $category ) {
		if ( !$category ) {
			return;
		}

		$categoryTitle = $this->titleFactory->makeTitleSafe( NS_CATEGORY, $category );
		if ( !$categoryTitle ) {
			$this->dieWithError( 'apierror-invalidcategory' );
		}

		$this->addTables( 'categorylinks' );
		$this->addJoinConds( [ 'categorylinks' => [
			'INNER JOIN',
			[
				'cl_to' => $categoryTitle->getDBkey(),
				'cl_from = log_page',
			]
		] ] );
	}

	/**
	 * @param string|null $mode
	 *
	 * @return void
	 */
	protected function limitToRelevantTags( ?string $mode ) {
		$mainTag   = MarkMajorChanges::getMainTagName();
		$secondTag = MarkMajorChanges::getSecondaryTagName();
		$db = $this->getDB();

		$mainTagLike = $db->buildLike( $db->anyString(), $mainTag, $db->anyString() );
		$secondTagLike = $db->buildLike( $db->anyString(), $secondTag, $db->anyString() );

		switch ( $mode ) {
			case 'onlymajor':
				$this->addWhere( 'log_params ' . $mainTagLike );
				break;
			case 'onlyminor':
				$this->addWhere( 'log_params ' . $secondTagLike );
				break;
			default:
				$this->addWhere( "log_params $mainTagLike OR log_params $secondTagLike" );
		}
	}

	/** @inheritDoc */
	public function getAllowedParams( $flags = 0 ) {
		// We set some params explicitly, so let's not allow them
		$allowedParams = parent::getAllowedParams();
		unset(
			$allowedParams['type']
		);

		// Set the action
		$allowedParams['action'][ApiBase::PARAM_DFLT] = 'tag/update';

		// Set the default limit a bit higher
		$allowedParams['limit'][ApiBase::PARAM_DFLT] = 25;

		// Add a URL property for convenience
		$allowedParams['prop'][ApiBase::PARAM_DFLT] = 'ids|title|type|user|timestamp|comment|details|url';
		$allowedParams['prop'][ApiBase::PARAM_TYPE][] = 'url';

		$allowedParams['mode'] = [
			ApiBase::PARAM_TYPE => MajorChangesLogPager::getAllowedModes()
		];

		$allowedParams['category'] = [
			ApiBase::PARAM_TYPE => 'string'
		];

		return $allowedParams;
	}

	/** @inheritDoc */
	protected function getExamplesMessages() {
		return [
			'action=query&list=majorchangeslogevents'
			=> 'apihelp-query+majorchangeslogevents-example-simple',
		];
	}
}
