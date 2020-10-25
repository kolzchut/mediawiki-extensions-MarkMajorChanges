<?php
/**
 * Query action to List the Major Changes log events, with optional filtering by various parameters.
 * This extends the regular ApiQueryLogEvents to add some filtering options.
 *
 * @ingroup API
 */

/*
 * @TODO:
 *  Add option to filter by tags added - arabic/majorchange/all
 * DONE
 */
class ApiQueryMajorChangesLogEvents extends ApiQueryLogEvents {

	public function __construct( ApiQuery $query, $moduleName ) {
		parent::__construct( $query, $moduleName );
	}

	private $fld_mode = false;

	public function execute() {
		// Always force 'tag/update' as the action. This also prevents someone from using this to
		// bypass the regular log protections
		$this->getRequest()->setVal( 'leaction', 'tag/update' );

		parent::execute();

		$params = $this->extractRequestParams();

		$prop = array_flip( $params['prop'] );
		// $this->fld_mode = isset( $prop['mode'] );

		// We want to give our consumers URLs right in the results, without further processing
		// Since we can't override extractRowInfo() we have to iterate over it all
		// Get the result data, remove it from the ApiResult object, modify it and push it back in
		$result = $this->getResult();
		$resultData = $this->getResult()->getResultData( [ 'query', $this->getModuleName() ] );
		$result->reset();
		$rows = $resultData ?: [];
		foreach ( $rows as &$row ) {
			if ( is_array( $row ) ) {
				if ( isset( $row[ 'pageid' ] ) && isset( $prop[ 'url' ] ) ) {
					$row[ 'url' ] = Title::newFromID( $row[ 'pageid' ] )->getFullURL();
				}
				unset( $row[ 'type' ], $row[ 'action' ] );
				$result->addValue( [ 'query', $this->getModuleName() ], null, $row );
			}
		}

	}

	private function extractRowInfo( $row ) {
	}

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
		$allowedParams['prop'][ApiBase::PARAM_DFLT] ='ids|title|type|user|timestamp|comment|details|url';
		$allowedParams['prop'][ApiBase::PARAM_TYPE][] ='url';

		return $allowedParams;
	}

	protected function getExamplesMessages() {
		return [
			'action=query&list=majorchangeslogevents'
			=> 'apihelp-query+majorchangeslogevents-example-simple',
		];
	}
}
