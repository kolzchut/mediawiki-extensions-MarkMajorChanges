<?php
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

	public static function onSkinTemplateNavigation( SkinTemplate &$sktemplate, array &$links ) {
		self::addMarkButton( $sktemplate, $links );

		return true;
	}

	public static function addMarkButton( SkinTemplate &$sktemplate, array &$links ) {
		$title = $sktemplate->getRelevantTitle();
		$user = $sktemplate->getUser();

		if ( $user->isAllowedAll( 'changetags', 'markmajorchange' ) ) {
			$urlParams = [
				'action' => 'markmajorchange',
			];

			$links['actions']['markmajorchange'] = [
				'text'	=> $sktemplate->msg( 'markmajorchanges-mark-btn' )->text(),
				'href'	=>  $title->getLocalURL( $urlParams )
			];
		};

	}
}


