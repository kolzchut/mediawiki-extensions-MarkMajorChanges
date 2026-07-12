<?php

namespace MediaWiki\Extension\MarkMajorChanges\Tests\Integration;

use MediaWiki\Extension\MarkMajorChanges\Hooks;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Title\Title;
use MediaWikiIntegrationTestCase;
use SkinTemplate;

/**
 * @covers \MediaWiki\Extension\MarkMajorChanges\Hooks::onSkinTemplateNavigation__Universal
 * @group MarkMajorChanges
 * @group Database
 */
class HooksNavigationTest extends MediaWikiIntegrationTestCase {

	/**
	 * Run the navigation hook for a given relevant title, with a permission
	 * manager that always grants the required rights, so the only thing left to
	 * gate the action link is the title check itself.
	 *
	 * @param Title $title The skin's relevant title.
	 * @return array The navigation links array after the hook runs.
	 */
	private function runHookFor( Title $title ): array {
		$permissionManager = $this->createMock( PermissionManager::class );
		$permissionManager->method( 'userHasAllRights' )->willReturn( true );

		$skin = $this->createMock( SkinTemplate::class );
		$skin->method( 'getRelevantTitle' )->willReturn( $title );
		$skin->method( 'getUser' )->willReturn(
			$this->getServiceContainer()->getUserFactory()->newAnonymous()
		);
		// msg() is only reached once the link is actually built.
		$skin->method( 'msg' )->willReturnCallback(
			static fn ( $key ) => wfMessage( $key )
		);

		$links = [ 'actions' => [] ];
		( new Hooks( $permissionManager ) )
			->onSkinTemplateNavigation__Universal( $skin, $links );

		return $links;
	}

	public function testActionShownOnExistingContentPage() {
		$title = $this->getExistingTestPage( 'MarkMajorChanges test page' )->getTitle();
		$links = $this->runHookFor( $title );
		$this->assertArrayHasKey(
			'markmajorchange', $links['actions'],
			'The action must appear on an existing content page.'
		);
	}

	public function testActionHiddenOnSpecialPage() {
		// Special pages cannot hold content: canExist() is false.
		$links = $this->runHookFor( Title::newFromText( 'Recentchanges', NS_SPECIAL ) );
		$this->assertArrayNotHasKey(
			'markmajorchange', $links['actions'],
			'The action must not appear on a Special: page.'
		);
	}

	public function testActionHiddenOnNonExistentPage() {
		$title = $this->getNonexistingTestPage( 'MarkMajorChanges missing page' )->getTitle();
		$links = $this->runHookFor( $title );
		$this->assertArrayNotHasKey(
			'markmajorchange', $links['actions'],
			'The action must not appear on a page with no revision to tag.'
		);
	}
}
