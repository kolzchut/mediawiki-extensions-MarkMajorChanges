<?php

namespace MediaWiki\Extension\MarkMajorChanges;

use MediaWiki\ChangeTags\Hook\ChangeTagsListActiveHook;
use MediaWiki\ChangeTags\Hook\ListDefinedTagsHook;
use MediaWiki\Hook\SkinTemplateNavigation__UniversalHook;
use MediaWiki\Permissions\PermissionManager;

/**
 * Hooks for the MarkMajorChanges extension.
 */
class Hooks implements
	SkinTemplateNavigation__UniversalHook,
	ListDefinedTagsHook,
	ChangeTagsListActiveHook
{

	public function __construct(
		private readonly PermissionManager $permissionManager
	) {
	}

	/**
	 * Extension registration callback.
	 *
	 * Override the core formatter for `tag/update` log entries with our own.
	 * This must happen at load time (during Setup), not at request time: in
	 * MW 1.43 LogFormatterFactory reads $wgLogActionsHandlers once into a
	 * ServiceOptions snapshot when the service is first built, so a runtime
	 * mutation no longer takes effect. It also cannot be done via
	 * extension.json "LogActionsHandlers", because that merge gives an existing
	 * core key precedence over the extension's value. See #7.
	 */
	public static function onRegistration(): void {
		global $wgLogActionsHandlers;
		$wgLogActionsHandlers['tag/update'] = MajorChangesTagLogFormatter::class;
	}

	/**
	 * Add our change tags to the list of defined tags.
	 *
	 * @param string[] &$tags
	 */
	public function onListDefinedTags( &$tags ) {
		$this->addTags( $tags );
	}

	/**
	 * Add our change tags to the list of active tags.
	 *
	 * @param string[] &$tags
	 */
	public function onChangeTagsListActive( &$tags ) {
		$this->addTags( $tags );
	}

	/**
	 * @param string[] &$tags
	 */
	private function addTags( array &$tags ): void {
		$tags[] = MarkMajorChanges::getMainTagName();
		$tags[] = MarkMajorChanges::getSecondaryTagName();
	}

	/**
	 * Add the "mark major change" action to the page toolbar for users who may
	 * apply the change tag.
	 *
	 * The action tags an existing revision (and reads its langlinks, categories,
	 * etc.), so it only makes sense on a real content page. Skip it on titles
	 * that cannot hold content — Special:/Media: pages, where canExist() is
	 * false — and on pages that do not yet exist, which have no revision to tag.
	 *
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/SkinTemplateNavigation::Universal
	 *
	 * @param \SkinTemplate $sktemplate The skin template on which the UI is built.
	 * @param array &$links Navigation links.
	 */
	public function onSkinTemplateNavigation__Universal( $sktemplate, &$links ): void {
		$title = $sktemplate->getRelevantTitle();
		$user = $sktemplate->getUser();

		if ( !$title->canExist() || !$title->exists() ) {
			return;
		}

		if ( $this->permissionManager->userHasAllRights( $user, 'changetags', 'markmajorchange' ) ) {
			$links['actions']['markmajorchange'] = [
				'text' => $sktemplate->msg( 'markmajorchanges-mark-btn' )->text(),
				'href' => $title->getLocalURL( [ 'action' => 'markmajorchange' ] ),
			];
		}
	}
}
