<?php
/**
 *  This file is part of PHP-Typography.
 *
 *  Copyright 2014-2018 Peter Putzer.
 *  Copyright 2009-2011 KINGdesk, LLC.
 *
 *  This program is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License along
 *  with this program; if not, write to the Free Software Foundation, Inc.,
 *  51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 *
 *  ***
 *
 *  @package mundschenk-at/php-typography
 *  @license http://www.gnu.org/licenses/gpl-2.0.html
 */

namespace PHP_Typography\Fixes\Node_Fixes;

use PHP_Typography\DOM;
use PHP_Typography\RE;
use PHP_Typography\Settings;
use PHP_Typography\U;

/**
 * Applies smart ordinal suffix (if enabled).
 *
 * Call before style_numbers.
 *
 * @author Peter Putzer <github@mundschenk.at>
 *
 * @since 5.0.0
 */
class Smart_Ordinal_Suffix_Fix extends Abstract_Node_Fix {

	const RE_ARABIC_ORDINALS = '/' . self::WORD_BOUNDARY_START . '(\d+)(' . self::ENGLISH_SUFFIXES . '|' . self::FRENCH_SUFFIXES . '|' . self::LATIN_SUFFIXES . ')' . self::WORD_BOUNDARY_END . '/Su';
	const ENGLISH_SUFFIXES   = 'st|nd|rd|th';
	const FRENCH_SUFFIXES    = 'er|re|e|ère|d|nd|nde|e|de|me|ème|è';
	const LATIN_SUFFIXES     = 'o';
	const RE_ROMAN_ORDINALS  = '/' . self::WORD_BOUNDARY_START . '(' . self::ROMAN_NUMERALS . ')(' . self::FRENCH_SUFFIXES . '|' . self::LATIN_SUFFIXES . ')' . self::WORD_BOUNDARY_END . '/Sxu';
	const ROMAN_NUMERALS     = '(?=[MDCLXVI])M*(?:C[MD]|D?C*)(?:X[CL]|L?X*)(?:I[XV]|V?I*)';

	// Zero-width spaces and soft hyphens should not be treated as word boundaries.
	const WORD_BOUNDARY_START = '\b(?![' . U::SOFT_HYPHEN . U::ZERO_WIDTH_SPACE . '])';
	const WORD_BOUNDARY_END   = '\b(?![' . U::SOFT_HYPHEN . U::ZERO_WIDTH_SPACE . '])';

	/**
	 * The replacement expression (depends on CSS class).
	 *
	 * @var string
	 */
	private $replacement;

	/**
	 * Creates a new smart ordinal suffix fixer.
	 *
	 * @param string|null $css_class       Optional. Default null.
	 * @param bool        $feed_compatible Optional. Default false.
	 */
	public function __construct( $css_class = null, $feed_compatible = false ) {
		parent::__construct( $feed_compatible );

		$ordinal_class     = empty( $css_class ) ? '' : ' class="' . $css_class . '"';
		$this->replacement = RE::escape_tags( "\$1<sup{$ordinal_class}>\$2</sup>" );
	}

	/**
	 * Apply the fix to a given textnode.
	 *
	 * @param \DOMText $textnode Required.
	 * @param Settings $settings Required.
	 * @param bool     $is_title Optional. Default false.
	 */
	public function apply( \DOMText $textnode, Settings $settings, $is_title = false ) {
		if ( empty( $settings['smartOrdinalSuffix'] ) ) {
			return;
		}

		$textnode->data = \preg_replace( [ self::RE_ARABIC_ORDINALS, self::RE_ROMAN_ORDINALS ], $this->replacement, $textnode->data );
	}
}
