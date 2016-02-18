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

/**
 * This class formats tag log entries.
 * It is an extension of the default TagLogFormatter,
 * in order to add diff links. The extemsion overrides
 * $wgLogActionsHandlers
 *
 * Parameters (one-based indexes):
 * 4::revid
 * 5::logid
 * 6:list:tagsAdded
 * 7:number:tagsAddedCount
 * 8:list:tagsRemoved
 * 9:number:tagsRemovedCount
 *
 */

class TagLogFormatterAdvanced extends TagLogFormatter {
	// This returns '' in the default TagLogFormatter
	public function getActionLinks() {
		$params = $this->getMessageParameters();

		if ( !isset( $params[3] )  ) {
			return '';
		}


		$oldid = $params[3];

		$diffLink = Linker::linkKnown(
			$this->entry->getTarget(),
			$this->msg( 'diff' )->escaped(),
			array(),
			array(
				'oldid' => $oldid,
				'diff' => 'prev',
			)
		);

		return $this->msg( 'parentheses' )->rawParams( $diffLink )->escaped();
	}
}
