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
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/SkinTemplateNavigation::Universal
	 *
	 * @param \SkinTemplate $sktemplate The skin template on which the UI is built.
	 * @param array &$links Navigation links.
	 */
	public function onSkinTemplateNavigation__Universal( $sktemplate, &$links ): void {
		$title = $sktemplate->getRelevantTitle();
		$user = $sktemplate->getUser();

		if ( $this->permissionManager->userHasAllRights( $user, 'changetags', 'markmajorchange' ) ) {
			$links['actions']['markmajorchange'] = [
				'text' => $sktemplate->msg( 'markmajorchanges-mark-btn' )->text(),
				'href' => $title->getLocalURL( [ 'action' => 'markmajorchange' ] ),
			];
		}
	}
}
