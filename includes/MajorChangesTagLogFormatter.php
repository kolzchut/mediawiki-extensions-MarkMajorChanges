<?php
/**
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 */

namespace MediaWiki\Extension\MarkMajorChanges;

use MediaWiki\MediaWikiServices;
use TagLogFormatter;

/**
 * This class formats tag log entries created by MarkMajorChanges, adding a
 * custom message and a diff link.
 *
 * It is registered as the system-wide handler for `tag/update` at load time
 * (Hooks::onRegistration), so it must behave exactly like the default
 * TagLogFormatter for every tag/update entry that is NOT ours. It discriminates
 * on the entry's own tags: an entry is "ours" iff every tag it touches is one
 * of the extension's tags (see MarkMajorChanges::getMainTagName /
 * getSecondaryTagName). This keeps unrelated tag log entries — including the
 * "mark done" entries, which carry a different tag — rendered by core.
 *
 * Registration happens at load time rather than at request time because in
 * MW 1.43 LogFormatterFactory snapshots $wgLogActionsHandlers into a
 * ServiceOptions the first time it is built; a runtime mutation (the previous
 * approach) no longer takes effect. See #7.
 *
 * Parameters (one-based indexes):
 * 4::revid
 * 5::logid
 * 6:list:tagsAdded
 * 7:number:tagsAddedCount
 * 8:list:tagsRemoved
 * 9:number:tagsRemovedCount
 *
 * LogFormatter subclasses are built with only a LogEntry (no service
 * injection), so the LinkRenderer is fetched from the service container.
 */
class MajorChangesTagLogFormatter extends TagLogFormatter {

	/**
	 * Whether this tag/update entry was created by MarkMajorChanges, i.e. every
	 * tag it touches belongs to the extension. Non-owned entries (ordinary tag
	 * changes, "mark done" entries) fall through to core rendering.
	 *
	 * @return bool
	 */
	private function isOwnEntry(): bool {
		$params = $this->entry->getParameters();
		$tags = array_merge(
			$params['6:list:tagsAdded'] ?? [],
			$params['8:list:tagsRemoved'] ?? []
		);
		if ( $tags === [] ) {
			return false;
		}

		$ownTags = [
			MarkMajorChanges::getMainTagName(),
			MarkMajorChanges::getSecondaryTagName(),
		];

		return array_diff( $tags, $ownTags ) === [];
	}

	/**
	 * Prevent user tool links after the username, but only for our own entries.
	 * @param bool $value
	 */
	public function setShowUserToolLinks( $value ) {
		if ( $this->isOwnEntry() ) {
			$this->linkFlood = false;
			return;
		}
		parent::setShowUserToolLinks( $value );
	}

	/** @inheritDoc */
	protected function getMessageKey() {
		if ( $this->isOwnEntry() ) {
			return 'logentry-majorchanges';
		}
		return parent::getMessageKey();
	}

	/**
	 * This returns '' in the default TagLogFormatter
	 * @inheritDoc
	 */
	public function getActionLinks() {
		if ( !$this->isOwnEntry() ) {
			return parent::getActionLinks();
		}

		$links = parent::getActionLinks();

		// Use the raw revid for the diff target; getMessageParameters()
		// replaces the same slot with a formatted link, not a usable id.
		$revId = $this->entry->getParameters()['4::revid'] ?? null;
		if ( $revId ) {
			$linkRenderer = MediaWikiServices::getInstance()->getLinkRenderer();
			$diffLink = $linkRenderer->makeKnownLink(
				$this->entry->getTarget(),
				$this->msg( 'diff' )->escaped(),
				[],
				[
					'oldid' => $revId,
					'diff' => 'prev',
				]
			);

			$links .= $this->msg( 'parentheses' )->rawParams( $diffLink )->escaped();

		}

		return $links;
	}
}
