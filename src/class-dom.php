<?php
/**
 *  This file is part of PHP-Typography.
 *
 *  Copyright 2014-2017 Peter Putzer.
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

namespace PHP_Typography;

/**
 * Some static methods for DOM manipulation.
 *
 * @since 4.2.0
 */
abstract class DOM {

	/**
	 * An array of block tag names.
	 *
	 * @var array
	 */
	private static $block_tags;

	/**
	 * Retrieves an array of block tag names.
	 *
	 * @param bool $reset Optional. Default false.
	 *
	 * @return array
	 */
	public static function block_tags( $reset = false ) {
		if ( empty( self::$block_tags ) || $reset ) {
			self::$block_tags = array_merge(
				array_flip( array_filter( array_keys( \Masterminds\HTML5\Elements::$html5 ), function( $tag ) {
					return \Masterminds\HTML5\Elements::isA( $tag, \Masterminds\HTML5\Elements::BLOCK_TAG );
				} ) ),
				array_flip( [ 'li', 'td', 'dt' ] ) // not included as "block tags" in current HTML5-PHP version.
			);
		}

		return self::$block_tags;
	}


	/**
	 * Converts \DOMNodeList to array;
	 *
	 * @param \DOMNodeList $list Required.
	 *
	 * @return array An associative array in the form ( $spl_object_hash => $node ).
	 */
	public static function nodelist_to_array( \DOMNodeList $list ) {
		$out = [];

		foreach ( $list as $node ) {
			$out[ spl_object_hash( $node ) ] = $node;
		}

		return $out;
	}

	/**
	 * Retrieves an array containing all the ancestors of the node. This could be done
	 * via an XPath query for "ancestor::*", but DOM walking is in all likelyhood faster.
	 *
	 * @param \DOMNode $node Required.
	 *
	 * @return array An array of \DOMNode.
	 */
	public static function get_ancestors( \DOMNode $node ) {
		$result = [];

		while ( ( $node = $node->parentNode ) && ( $node instanceof \DOMElement ) ) { // @codingStandardsIgnoreLine.
			$result[] = $node;
		}

		return $result;
	}

	/**
	 * Checks whether the \DOMNode has one of the given classes.
	 * If $tag is a \DOMText, the parent DOMElement is checked instead.
	 *
	 * @param \DOMNode     $tag        An element or textnode.
	 * @param string|array $classnames A single classname or an array of classnames.
	 *
	 * @return bool True if the element has any of the given class(es).
	 */
	public static function has_class( \DOMNode $tag, $classnames ) {
		if ( $tag instanceof \DOMText ) {
			$tag = $tag->parentNode; // @codingStandardsIgnoreLine.
		}

		// Bail if we are not working with a tag or if there is no classname.
		if ( ! ( $tag instanceof \DOMElement ) || empty( $classnames ) ) {
			return false;
		}

		// Ensure we always have an array of classnames.
		if ( ! is_array( $classnames ) ) {
			$classnames = [ $classnames ];
		}

		if ( $tag->hasAttribute( 'class' ) ) {
			$tag_classes = array_flip( explode( ' ', $tag->getAttribute( 'class' ) ) );

			foreach ( $classnames as $classname ) {
				if ( isset( $tag_classes[ $classname ] ) ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Retrieves the last character of the previous \DOMText sibling (if there is one).
	 *
	 * @param \DOMNode $node The content node.
	 *
	 * @return string A single character (or the empty string).
	 */
	public static function get_prev_chr( \DOMNode $node ) {
		return self::get_adjacent_chr( $node, -1, 1, [ __CLASS__, 'get_previous_textnode' ] );
	}

	/**
	 * Retrieves the first character of the next \DOMText sibling (if there is one).
	 *
	 * @param \DOMNode $node The content node.
	 *
	 * @return string A single character (or the empty string).
	 */
	public static function get_next_chr( \DOMNode $node ) {
		return self::get_adjacent_chr( $node, 0, 1, [ __CLASS__, 'get_next_textnode' ] );
	}

	/**
	 * Retrieves a character from the given \DOMNode.
	 *
	 * @since 5.0.0
	 *
	 * @param  \DOMNode $node         Required.
	 * @param  int      $position     The position parameter for `substr`.
	 * @param  int      $length       The length parameter for `substr`.
	 * @param  callable $get_textnode A function to retrieve the \DOMText from the node.
	 *
	 * @return string The character or an empty string.
	 */
	private static function get_adjacent_chr( \DOMNode $node, $position, $length, callable $get_textnode ) {
		$textnode = $get_textnode( $node );

		if ( isset( $textnode ) && isset( $textnode->data ) ) {
			// Determine encoding.
			$func = Strings::functions( $textnode->data );

			if ( ! empty( $func ) ) {
				return preg_replace( '/\p{C}/Su', '', $func['substr']( $textnode->data, $position, $length ) );
			}
		}

		return '';
	}

	/**
	 * Retrieves the previous \DOMText sibling (if there is one).
	 *
	 * @param \DOMNode|null $node Optional. The content node. Default null.
	 *
	 * @return \DOMText|null Null if $node is a block-level element or no text sibling exists.
	 */
	public static function get_previous_textnode( \DOMNode $node = null ) {
		return self::get_adjacent_textnode( function( &$another_node = null ) {
			$another_node = $another_node->previousSibling;
			return self::get_last_textnode( $another_node );
		}, [ __CLASS__, __FUNCTION__ ], $node );
	}

	/**
	 * Retrieves the next \DOMText sibling (if there is one).
	 *
	 * @param \DOMNode|null $node Optional. The content node. Default null.
	 *
	 * @return \DOMText|null Null if $node is a block-level element or no text sibling exists.
	 */
	public static function get_next_textnode( \DOMNode $node = null ) {
		return self::get_adjacent_textnode( function( &$another_node = null ) {
			$another_node = $another_node->nextSibling;
			return self::get_first_textnode( $another_node );
		}, [ __CLASS__, __FUNCTION__ ], $node );
	}

	/**
	 * Retrieves an adjacent \DOMText sibling if there is one.
	 *
	 * @since 5.0.0
	 *
	 * @param callable      $iterate             Takes a reference \DOMElement and returns a \DOMText (or null).
	 * @param callable      $get_adjacent_parent Takes a single \DOMElement parameter and returns a \DOMText (or null).
	 * @param \DOMNode|null $node                Optional. The content node. Default null.
	 *
	 * @return \DOMText|null Null if $node is a block-level element or no text sibling exists.
	 */
	private static function get_adjacent_textnode( callable $iterate, callable $get_adjacent_parent, \DOMNode $node = null ) {
		if ( ! isset( $node ) ) {
			return null;
		} elseif ( $node instanceof \DOMElement && isset( self::$block_tags[ $node->tagName ] ) ) {
			return null;
		}

		/**
		 * The result node.
		 *
		 * @var \DOMText|null
		 */
		$adjacent      = null;
		$iterated_node = $node;

		// Iterate to find adjacent node.
		while ( null !== $iterated_node && null === $adjacent ) {
			$adjacent = $iterate( $iterated_node );
		}

		// Last ressort.
		if ( null === $adjacent ) {
			$adjacent = $get_adjacent_parent( $node->parentNode );
		}

		return $adjacent;
	}

	/**
	 * Retrieves the first \DOMText child of the element. Block-level child elements are ignored.
	 *
	 * @param \DOMNode|null $node      Optional. Default null.
	 * @param bool          $recursive Should be set to true on recursive calls. Optional. Default false.
	 *
	 * @return \DOMText|null The first child of type \DOMText, the element itself if it is of type \DOMText or null.
	 */
	public static function get_first_textnode( \DOMNode $node = null, $recursive = false ) {
		return self::get_edge_textnode( [ __CLASS__, __FUNCTION__ ], $node, $recursive, false );
	}

	/**
	 * Retrieves the last \DOMText child of the element. Block-level child elements are ignored.
	 *
	 * @param \DOMNode|null $node      Optional. Default null.
	 * @param bool          $recursive Should be set to true on recursive calls. Optional. Default false.
	 *
	 * @return \DOMText|null The last child of type \DOMText, the element itself if it is of type \DOMText or null.
	 */
	public static function get_last_textnode( \DOMNode $node = null, $recursive = false ) {
		return self::get_edge_textnode( [ __CLASS__, __FUNCTION__ ], $node, $recursive, true );
	}

	/**
	 * Retrieves an edge \DOMText child of the element specified by the callable.
	 * Block-level child elements are ignored.
	 *
	 * @since 5.0.0
	 *
	 * @param callable      $get_textnode Takes two parameters, a \DOMNode and a boolean flag for recursive calls.
	 * @param \DOMNode|null $node         Optional. Default null.
	 * @param bool          $recursive    Should be set to true on recursive calls. Optional. Default false.
	 * @param bool          $reverse      Whether to iterate forward or backward. Optional. Default false.
	 *
	 * @return \DOMText|null The last child of type \DOMText, the element itself if it is of type \DOMText or null.
	 */
	private static function get_edge_textnode( callable $get_textnode, \DOMNode $node = null, $recursive = false, $reverse = false ) {
		if ( ! isset( $node ) ) {
			return null;
		}

		if ( $node instanceof \DOMText ) {
			return $node;
		} elseif ( ! $node instanceof \DOMElement ) {
			// Return null if $node is neither \DOMText nor \DOMElement.
			return null;
		} elseif ( $recursive && isset( self::$block_tags[ $node->tagName ] ) ) {
			return null;
		}

		$edge_textnode = null;

		if ( $node->hasChildNodes() ) {
			$children    = $node->childNodes;
			$max         = $children->length;
			$index       = $reverse ? $max - 1 : 0;
			$incrementor = $reverse ? -1 : +1;

			while ( $index >= 0 && $index < $max && null === $edge_textnode ) {
				$edge_textnode = $get_textnode( $children->item( $index ), true );
				$index += $incrementor;
			}
		}

		return $edge_textnode;
	}

	/**
	 * Returns the nearest block-level parent (or null).
	 *
	 * @param \DOMNode $node Required.
	 *
	 * @return \DOMElement|null
	 */
	public static function get_block_parent( \DOMNode $node ) {
		$parent = $node->parentNode;
		if ( ! $parent instanceof \DOMElement ) {
			return null;
		}

		while ( ! isset( self::$block_tags[ $parent->tagName ] ) && $parent->parentNode instanceof \DOMElement ) {
			/**
			 * The parent is sure to be a \DOMElement.
			 *
			 * @var \DOMElement
			 */
			$parent = $parent->parentNode;
		}

		return $parent;
	}

	/**
	 * Retrieves the tag name of the nearest block-level parent.
	 *
	 * @param \DOMNode $node A node.

	 * @return string The tag name (or the empty string).
	 */
	public static function get_block_parent_name( \DOMNode $node ) {
		$parent = self::get_block_parent( $node );

		if ( ! empty( $parent ) ) {
			return $parent->tagName;
		} else {
			return '';
		}
	}
}

/**
 *  Initialize block tags on load.
 */
DOM::block_tags(); // @codeCoverageIgnore
