<?php

namespace MediaWiki\Extension\MarkMajorChanges\Tests\Integration;

use ManualLogEntry;
use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\MarkMajorChanges\MajorChangesTagLogFormatter;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\Title\Title;
use MediaWikiIntegrationTestCase;
use Wikimedia\TestingAccessWrapper;

/**
 * @covers \MediaWiki\Extension\MarkMajorChanges\MajorChangesTagLogFormatter
 * @group MarkMajorChanges
 */
class MajorChangesTagLogFormatterTest extends MediaWikiIntegrationTestCase {

	/**
	 * Build a tag/update log entry and format it through the real
	 * LogFormatterFactory, exactly as MediaWiki does when rendering a log line.
	 * Going through the factory is deliberate: it proves the formatter is
	 * actually selected for tag/update (Hooks::onRegistration), which is the
	 * behaviour that broke under MW 1.43. See #7.
	 *
	 * @param array $params Raw log parameters (e.g. 6:list:tagsAdded)
	 * @return \LogFormatter
	 */
	private function formatterFor( array $params ) {
		// Stub the LinkRenderer so getActionLinks() can build the diff link
		// without a database (tests run DB-disabled). getActionLinks() pulls the
		// renderer from the service container, so replacing the service is enough.
		$linkRenderer = $this->createMock( LinkRenderer::class );
		$linkRenderer->method( 'makeKnownLink' )->willReturnCallback(
			static fn ( $target, $text = null, $extra = [], $query = [] ) =>
				'<a href="?' . http_build_query( $query ) . '">'
				. ( is_string( $text ) ? $text : 'link' ) . '</a>'
		);
		$this->setService( 'LinkRenderer', $linkRenderer );

		$entry = new ManualLogEntry( 'tag', 'update' );
		$entry->setPerformer( $this->getServiceContainer()->getUserFactory()->newAnonymous() );
		$entry->setTarget( Title::newFromText( 'Main Page' ) );
		$entry->setParameters( $params );

		$formatter = $this->getServiceContainer()
			->getLogFormatterFactory()
			->newFromEntry( $entry );

		$context = new RequestContext();
		$context->setLanguage( 'en' );
		$formatter->setContext( $context );

		return $formatter;
	}

	/**
	 * The extension's own entries: add-only, tagged with the extension's tags.
	 * Note the missing 8/9 "removed" params — this is the real historical shape
	 * that tripped core TagLogFormatter's "Undefined array key" warning once the
	 * runtime formatter override stopped working.
	 */
	private static function ownParams(): array {
		return [
			'4::revid' => 601491,
			'6:list:tagsAdded' => [ 'majorchange' ],
			'7:number:tagsAddedCount' => 1,
		];
	}

	public function testTagUpdateIsHandledByOurFormatter() {
		$formatter = $this->formatterFor( self::ownParams() );
		$this->assertInstanceOf(
			MajorChangesTagLogFormatter::class,
			$formatter,
			'onRegistration must register our formatter for tag/update'
		);
	}

	public function testOwnEntryUsesCustomMessageKey() {
		$formatter = $this->formatterFor( self::ownParams() );
		// getMessageKey() reads only parameters, and our path never touches
		// 9:number:tagsRemovedCount — so this also asserts the regression
		// warning is gone (the test would fail on any PHP warning).
		$this->assertSame(
			'logentry-majorchanges',
			TestingAccessWrapper::newFromObject( $formatter )->getMessageKey()
		);
	}

	public function testOwnEntryAddsDiffLink() {
		$links = $this->formatterFor( self::ownParams() )->getActionLinks();
		$this->assertStringContainsString( 'diff=prev', $links );
		$this->assertStringContainsString( 'oldid=601491', $links );
	}

	/**
	 * A run-of-the-mill tag change (unrelated tag) must fall through to core
	 * rendering, not be hijacked with the major-changes message.
	 */
	public function testForeignEntryFallsBackToCore() {
		$formatter = $this->formatterFor( [
			'4::revid' => 601491,
			'6:list:tagsAdded' => [ 'mw-reverted' ],
			'7:number:tagsAddedCount' => 1,
			'8:list:tagsRemoved' => [],
			'9:number:tagsRemovedCount' => 0,
		] );

		$this->assertStringStartsWith(
			'logentry-tag-update',
			TestingAccessWrapper::newFromObject( $formatter )->getMessageKey()
		);
		$this->assertSame( '', $formatter->getActionLinks() );
	}

	/**
	 * The "mark done" flow tags entries with a *different* tag
	 * ("שינוי מהותי טופל"), which is not one of the extension's own tags, so
	 * those entries must also render through core.
	 */
	public function testHandledTagEntryFallsBackToCore() {
		$formatter = $this->formatterFor( [
			'4::revid' => null,
			'5::logid' => 999,
			'6:list:tagsAdded' => [ 'שינוי מהותי טופל' ],
			'7:number:tagsAddedCount' => 1,
			'8:list:tagsRemoved' => [],
			'9:number:tagsRemovedCount' => 0,
		] );

		$this->assertStringStartsWith(
			'logentry-tag-update',
			TestingAccessWrapper::newFromObject( $formatter )->getMessageKey()
		);
	}
}
