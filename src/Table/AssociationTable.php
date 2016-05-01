<?php
/**
 * Contains the AssociationTable class.
 *
 * @author    Iron Bound Designs
 * @since     2.0
 * @license   MIT
 * @copyright Iron Bound Designs, 2016.
 */

namespace IronBound\DB\Table;

use Doctrine\Common\Inflector\Inflector;
use IronBound\DB\Table\Column\Foreign;

/**
 * Class AssociationTable
 * @package IronBound\DB\Table
 */
class AssociationTable extends BaseTable {

	/**
	 * @var Table
	 */
	protected $table_a;

	/**
	 * @var Table
	 */
	protected $table_b;

	/**
	 * @var array
	 */
	protected $overrides = array();

	/**
	 * @var string
	 */
	protected $table_name;

	/**
	 * @var string
	 */
	protected $slug;

	/**
	 * @var string
	 */
	protected $col_a;

	/**
	 * @var string
	 */
	protected $col_b;

	/**
	 * AssociationTable constructor.
	 *
	 * @param Table $table_a
	 * @param Table $table_b
	 * @param array $overrides
	 */
	public function __construct( Table $table_a, Table $table_b, array $overrides = array() ) {

		$this->table_a   = $table_a;
		$this->table_b   = $table_b;
		$this->overrides = $overrides;

		if ( ! empty( $overrides['slug'] ) ) {
			$this->slug = $overrides['slug'];
		} else {
			$this->slug = $table_a->get_slug() . '-' . $table_b->get_slug();
		}

		if ( ! empty( $overrides['table_name'] ) ) {
			$this->table_name = $overrides['table_name'];
		} else {
			$this->table_name = str_replace( '-', '_', "{$table_a->get_slug()}_to_{$table_b->get_slug()}" );
		}

		if ( ! empty( $overrides['col_a'] ) ) {
			$this->col_a = $overrides['col_a'];
		} else {
			$this->col_a = $this->build_column_name_for_table( $table_a );
		}

		if ( ! empty( $overrides['col_b'] ) ) {
			$this->col_b = $overrides['col_b'];
		} else {
			$this->col_b = $this->build_column_name_for_table( $table_b );
		}
	}

	/**
	 * Get the a connecting table.
	 *
	 * @since 2.0
	 *
	 * @return Table
	 */
	public function get_table_a() {
		return $this->table_a;
	}

	/**
	 * Get the b connecting table.
	 *
	 * @since 2.0
	 *
	 * @return Table
	 */
	public function get_table_b() {
		return $this->table_b;
	}

	/**
	 * Get the column name for the a connecting table.
	 *
	 * @since 2.0
	 *
	 * @return string
	 */
	public function get_col_a() {
		return $this->col_a;
	}

	/**
	 * Get the column name for the b connecting table.
	 *
	 * @since 2.0
	 *
	 * @return string
	 */
	public function get_col_b() {
		return $this->col_b;
	}

	/**
	 * Build the column name for a table.
	 *
	 * @since 2.0
	 *
	 * @param Table $table
	 *
	 * @return string
	 */
	protected function build_column_name_for_table( Table $table ) {

		$basename  = $this->class_basename( $table );
		$tableized = Inflector::tableize( $basename );

		$parts         = explode( '_', $tableized );
		$last_plural   = array_pop( $parts );
		$last_singular = Inflector::singularize( $last_plural );
		$parts[]       = $last_singular;

		$column_name = implode( '_', $parts );
		$column_name .= '_' . $table->get_primary_key();

		return $column_name;
	}

	/**
	 * Get the basename for a class.
	 *
	 * @since 2.0
	 *
	 * @param string|object $class
	 *
	 * @return string
	 */
	protected function class_basename( $class ) {

		$class = is_object( $class ) ? get_class( $class ) : $class;

		return basename( str_replace( '\\', '/', $class ) );
	}

	/**
	 * @inheritDoc
	 */
	public function get_table_name( \wpdb $wpdb ) {
		return "{$wpdb->prefix}{$this->table_name}";
	}

	/**
	 * @inheritDoc
	 */
	public function get_slug() {
		return $this->slug;
	}

	/**
	 * @inheritDoc
	 */
	public function get_columns() {
		return array(
			$this->col_a => new Foreign( $this->col_a, $this->table_a ),
			$this->col_b => new Foreign( $this->col_b, $this->table_b )
		);
	}

	/**
	 * @inheritDoc
	 */
	public function get_column_defaults() {
		return array(
			$this->col_a => '',
			$this->col_b => ''
		);
	}

	/**
	 * @inheritDoc
	 */
	public function get_primary_key() {
		return '';
	}

	/**
	 * @inheritDoc
	 */
	public function get_version() {
		return $this->table_a->get_version() + $this->table_b->get_version();
	}
}