<?php

use MediaWiki\MediaWikiServices;

/**
 * Hooks for MarkMajorChanges extension
 *
 * @file
 * @ingroup Extensions
 */

class MarkMajorChangesHooks {
	/**
	 * Add our new tag to the array of existing tags.
	 *
	 * @param array &$tags
	 * @return bool
	 */
	public static function registerChangeTags( array &$tags ) {
		$tags[] = MarkMajorChanges::getMainTagName();
		$tags[] = MarkMajorChanges::getSecondaryTagName();

		return true;
	}

	/**
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/SkinTemplateNavigation
	 *
	 * @param SkinTemplate &$sktemplate The skin template on which the UI is built.
	 * @param array &$links Navigation links.
	 */
	public static function onSkinTemplateNavigation( SkinTemplate &$sktemplate, array &$links ) {
		self::addMarkButton( $sktemplate, $links );
	}

	/**
	 * @param SkinTemplate &$sktemplate
	 * @param array &$links
	 *
	 * @return void
	 */
	public static function addMarkButton( SkinTemplate &$sktemplate, array &$links ) {
		$title = $sktemplate->getRelevantTitle();
		$user = $sktemplate->getUser();
		$permissionManager = MediaWikiServices::getInstance()->getPermissionManager();

		if ( $permissionManager->userHasAllRights( $user, 'changetags', 'markmajorchange' ) ) {
			$urlParams = [
				'action' => 'markmajorchange'
			];

			$links['actions']['markmajorchange'] = [
				'text'	=> $sktemplate->msg( 'markmajorchanges-mark-btn' )->text(),
				'href'	=> $title->getLocalURL( $urlParams )
			];
		}
	}
}
