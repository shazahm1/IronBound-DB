<?php
/**
 * Abstract model class for models built upon our DB table.
 *
 * @author      Iron Bound Designs
 * @since       1.0
 * @copyright   2015 (c) Iron Bound Designs.
 * @license     GPLv2
 */

namespace IronBound\DB;

use IronBound\Cache\Cacheable;
use IronBound\Cache\Cache;
use IronBound\DB\Helpers\ColumnModelProxy;
use IronBound\DB\Table\Table;

/**
 * Class Model
 *
 * @package IronBound\DB;
 */
abstract class Model implements Cacheable, \Serializable {

	/// Global Configuration

	/**
	 * @var Manager
	 */
	protected static $_db_manager;

	/// Model Configuration

	/**
	 * Whether all attributes can be automatically assigned.
	 *
	 * @var bool
	 */
	protected static $_unguarded = true;

	/// Instance Configuration

	/**
	 * List of attributes that are automatically filled.
	 *
	 * @var array
	 */
	protected $_fillable = array();

	/**
	 * Raw attribute data.
	 *
	 * @var array
	 */
	protected $_attributes = array();

	/**
	 * Original state of the model.
	 *
	 * Updated whenever the model is saved.
	 *
	 * @var array
	 */
	protected $_original = array();

	/**
	 * Cache of attribute values.
	 *
	 * @var array
	 */
	protected $_attribute_value_cache = array();

	/**
	 * @var bool
	 */
	protected $_exists = false;

	/**
	 * Model constructor.
	 *
	 * @since 2.0
	 *
	 * @param array|object $data
	 */
	public function __construct( $data = array() ) {
		$this->init( (object) $data );

		if ( ! isset( static::$_db_manager ) ) {
			static::$_db_manager = new Manager();
		}
	}

	/**
	 * Fill data on this model automatically.
	 *
	 * @since 2.0
	 *
	 * @param array $data
	 *
	 * @return $this
	 */
	protected function fill( array $data = array() ) {

		foreach ( $data as $column => $value ) {

			if ( $this->is_fillable( $column ) ) {
				$this->set_attribute( $column, $value );
			}
		}

		return $this;
	}

	/**
	 * Set an attribute.
	 *
	 * @since 2.0
	 *
	 * @param string $attribute
	 * @param mixed  $value
	 *
	 * @return $this
	 */
	public function set_attribute( $attribute, $value ) {

		unset( $this->_attribute_value_cache[ $attribute ] );

		$setter = "set_{$attribute}_attribute";

		if ( method_exists( $this, $setter ) ) {
			return $this->{$setter}( $value );
		}

		$this->_attributes[ $attribute ] = $value;

		return $this;
	}

	/**
	 * Get an attribute value.
	 *
	 * @since 2.0
	 *
	 * @param string $attribute
	 *
	 * @return mixed|null
	 */
	public function get_attribute( $attribute ) {

		if ( array_key_exists( $attribute, $this->_attributes ) ) {

			if ( isset( $this->_attribute_value_cache[ $attribute ] ) ) {
				$value = $this->_attribute_value_cache[ $attribute ];
			} else {
				$value = $this->_attributes[ $attribute ];

				// only update the attribute value cache if we have a raw value from the db
				if ( is_scalar( $value ) ) {
					$columns = static::get_table()->get_columns();
					$value   = $columns[ $attribute ]->convert_raw_to_value( $value );

					$this->_attribute_value_cache[ $attribute ] = $value;
				}
			}

			$getter = "get_{$attribute}_attribute";

			if ( method_exists( $this, $getter ) ) {
				$value = $this->{$getter}( $value );
			}

			return $value;
		}

		return null;
	}

	/**
	 * Determine if a given attribute is fillable.
	 *
	 * @since 2.0
	 *
	 * @param string $column
	 *
	 * @return bool
	 */
	protected function is_fillable( $column ) {

		if ( static::$_unguarded ) {
			return true;
		}

		if ( in_array( $this->_fillable, $column ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Sync the model's original attributes with its current state.
	 *
	 * @since 2.0
	 *
	 * @return $this
	 */
	public function sync_original() {
		$this->_original = $this->_attributes;

		return $this;
	}

	/**
	 * Sync an individual attribute.
	 *
	 * @since 2.0
	 *
	 * @param string $attribute
	 *
	 * @return $this
	 */
	public function sync_original_attribute( $attribute ) {
		$this->_original[ $attribute ] = $this->_attributes[ $attribute ];

		return $this;
	}


	/**
	 * Determine if the model or given attribute(s) have been modified.
	 *
	 * @since 2.0
	 *
	 * @param array|string...|null $attributes
	 *
	 * @return bool
	 */
	public function is_dirty( $attributes = null ) {

		$dirty = $this->get_dirty();

		if ( is_null( $attributes ) ) {
			return count( $dirty ) > 0;
		}

		if ( ! is_array( $attributes ) ) {
			$attributes = func_get_args();
		}

		foreach ( $attributes as $attribute ) {
			if ( array_key_exists( $attribute, $dirty ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Get the attributes that have been changed since last sync.
	 *
	 * @since 2.0
	 *
	 * @return array
	 */
	public function get_dirty() {
		$dirty = array();

		foreach ( $this->_attributes as $key => $value ) {
			if ( ! array_key_exists( $key, $this->_original ) ) {
				$dirty[ $key ] = $value;
			} elseif ( $value !== $this->_original[ $key ] && ! $this->original_is_numerically_equivalent( $key ) ) {
				$dirty[ $key ] = $value;
			}
		}

		return $dirty;
	}

	/**
	 * Determine if the new and old values for a given key are numerically equivalent.
	 *
	 * @since 2.0
	 *
	 * @param string $key
	 *
	 * @return bool
	 */
	protected function original_is_numerically_equivalent( $key ) {

		$current = $this->_attributes[ $key ];

		$original = $this->_original[ $key ];

		return is_numeric( $current ) && is_numeric( $original ) &&
		       strcmp( (string) $current, (string) $original ) === 0;
	}

	/**
	 * Retrieve this object.
	 *
	 * @since 1.0
	 *
	 * @param int|string $pk Primary key of this record.
	 *
	 * @returns self|null
	 */
	public static function get( $pk ) {

		$data = self::get_data_from_pk( $pk );

		if ( $data ) {

			$object = new static( (object) $data );

			if ( ! static::is_data_cached( $pk ) ) {
				Cache::update( $object );
			}

			return $object;
		} else {
			return null;
		}
	}

	/**
	 * Convert an array of raw data to their corresponding values.
	 *
	 * @since 2.0
	 *
	 * @param array $data
	 *
	 * @return array
	 */
	protected static function convert_raw_data_to_values( $data ) {

		$columns = static::get_table()->get_columns();
		$mapped  = array();

		foreach ( (array) $data as $column => $value ) {
			$mapped[ $column ] = $columns[ $column ]->convert_raw_to_value( $value, $data );
		}

		return $mapped;
	}

	/**
	 * Get data for a primary key.
	 *
	 * @since 1.0
	 *
	 * @param int|string $pk Primary key for this record.
	 *
	 * @return \stdClass|null
	 */
	protected static function get_data_from_pk( $pk ) {

		$data = Cache::get( $pk, static::get_cache_group() );

		if ( ! $data ) {
			$data = static::make_query_object()->get( $pk );
		}

		return $data ? (object) $data : null;
	}

	/**
	 * Check if data is cached.
	 *
	 * @since 1.0
	 *
	 * @param int|string $pk Primary key for this record.
	 *
	 * @return bool
	 */
	protected static function is_data_cached( $pk ) {

		$data = Cache::get( $pk, static::get_cache_group() );

		return ! empty( $data );
	}

	/**
	 * Init an object.
	 *
	 * @since 1.0
	 *
	 * @param \stdClass $data
	 */
	protected function init( \stdClass $data ) {

		$this->sync_original();
		$this->fill( (array) $data );
		$this->_exists = (bool) $this->get_pk();
	}

	/**
	 * Get the table object for this model.
	 *
	 * @since 1.0
	 *
	 * @returns Table
	 */
	protected static function get_table() {
		// override this in child classes.
		throw new \UnexpectedValueException();
	}

	/**
	 * Update a certain value.
	 *
	 * @since 1.0
	 *
	 * @param string $key   DB column to update.
	 * @param mixed  $value New value.
	 *
	 * @return bool
	 */
	protected function update( $key, $value ) {

		$columns = static::get_table()->get_columns();

		$data = array(
			$key => $columns[ $key ]->prepare_for_storage( $value )
		);

		$res = static::make_query_object()->update( $this->get_pk(), $data );

		if ( $res ) {
			Cache::update( $this );
		}

		return $res;
	}

	/**
	 * Does this model exist yet.
	 *
	 * @since 2.0
	 *
	 * @return bool
	 */
	public function exists() {
		return $this->_exists;
	}

	/**
	 * Persist this model's changes to the database.
	 *
	 * @since 2.0
	 *
	 * @return bool
	 *
	 * @throws Exception
	 */
	public function save() {

		if ( $this->exists() ) {
			$saved = $this->do_save_as_update();
		} else {
			$saved = $this->do_save_as_insert();
		}

		if ( $saved ) {
			$this->finish_save();
		}

		return $saved;
	}

	/**
	 * Save model as an update query.
	 *
	 * @since 2.0
	 *
	 * @return bool
	 *
	 * @throws Exception
	 */
	protected function do_save_as_update() {

		$dirty = $this->get_dirty();

		if ( ! $dirty ) {
			return true;
		}

		$result = static::make_query_object()->update( $this->get_pk(), $dirty );

		if ( $result ) {
			Cache::update( $this );
		}

		return $result;
	}

	/**
	 * Save model as an insert query.
	 *
	 * @since 2.0
	 *
	 * @return bool
	 *
	 * @throws Exception
	 */
	protected function do_save_as_insert() {

		$insert_id = static::make_query_object()->insert( $this->_attributes );

		if ( $insert_id ) {
			$this->set_attribute( static::get_table()->get_primary_key(), $insert_id );
		}

		$default_columns_to_fill = array();

		foreach ( static::get_table()->get_columns() as $name => $column ) {

			if ( ! array_key_exists( $name, $this->_attributes ) ) {
				$default_columns_to_fill[] = $name;
			}
		}

		$default_values = (array) static::make_query_object()->get(
			$this->get_pk(), $default_columns_to_fill
		);

		foreach ( $default_values as $column => $value ) {
			$this->set_attribute( $column, $value );
		}

		$this->_exists = true;

		Cache::update( $this );

		return true;
	}

	/**
	 * Perform cleanup after a save has occurred.
	 *
	 * @since 2.0
	 */
	protected function finish_save() {
		$this->sync_original();
	}

	/**
	 * Delete this object.
	 *
	 * @since 1.0
	 *
	 * @throws Exception
	 */
	public function delete() {

		static::make_query_object()->delete( $this->get_pk() );

		Cache::delete( $this );
	}

	/**
	 * Make a query object.
	 *
	 * @since 1.2
	 *
	 * @return Query\Simple_Query|null
	 */
	protected static function make_query_object() {
		return static::$_db_manager->make_simple_query_object( static::get_table()->get_slug() );
	}

	/**
	 * Get the data we'd like to cache.
	 *
	 * This is a bit magical. It iterates through all of the table columns,
	 * and checks if a getter for that method exists. If so, it pulls in that
	 * value. Otherwise, it will pull in the default value. If you'd like to
	 * customize this you should override this function in your child model
	 * class.
	 *
	 * @since 1.0
	 *
	 * @return array
	 */
	public function get_data_to_cache() {

		$data = $this->_attributes;

		$columns = static::get_table()->get_columns();

		foreach ( $data as $column => $value ) {
			$data[ $column ] = $columns[ $column ]->prepare_for_storage( $value );
		}

		return $data;
	}

	/**
	 * Get the cache group for this record.
	 *
	 * By default this returns a string in the following format
	 * "df-{$table_slug}".
	 *
	 * @since 1.0
	 *
	 * @return string
	 */
	public static function get_cache_group() {
		return static::get_table()->get_slug();
	}

	/**
	 * (PHP 5 &gt;= 5.1.0)<br/>
	 * String representation of object
	 *
	 * @link http://php.net/manual/en/serializable.serialize.php
	 * @return string the string representation of the object or null
	 */
	public function serialize() {
		return serialize( array(
			'pk'       => $this->get_pk(),
			'fillable' => $this->_fillable,
			'original' => $this->_original,
		) );
	}

	/**
	 * (PHP 5 &gt;= 5.1.0)<br/>
	 * Constructs the object
	 *
	 * @link http://php.net/manual/en/serializable.unserialize.php
	 *
	 * @param string $serialized <p>
	 *                           The string representation of the object.
	 *                           </p>
	 *
	 * @return void
	 */
	public function unserialize( $serialized ) {
		$data = unserialize( $serialized );

		$this->init( self::get_data_from_pk( $data['pk'] ) );
		$this->_fillable = $data['fillable'];
		$this->_original = $data['original'];
	}

	/**
	 * Magic method to retrieve an attribute from the model.
	 *
	 * @since 2.0
	 *
	 * @param string $name
	 *
	 * @return mixed|null
	 */
	public function __get( $name ) {
		return $this->get_attribute( $name );
	}

	/**
	 * Magic method to set an attribute on the model.
	 *
	 * @since 2.0
	 *
	 * @param string $name
	 * @param mixed  $value
	 *
	 * @return void
	 */
	public function __set( $name, $value ) {
		$this->set_attribute( $name, $value );
	}

	/**
	 * Magic method to determine if an attribute exists on the model.
	 *
	 * @since 2.0
	 *
	 * @param string $name
	 *
	 * @return bool
	 */
	public function __isset( $name ) {
		return $this->get_attribute( $name ) !== null;
	}

	/**
	 * Magic method to remove an attribute from the model.
	 *
	 * @since 2.0
	 *
	 * @param string $name
	 */
	public function __unset( $name ) {
		$this->_attributes[ $name ] = null;
	}

	/**
	 * Set the DB Manager to use for this model.
	 *
	 * @since 1.2
	 *
	 * @param Manager $manager
	 */
	public static function set_db_manager( Manager $manager ) {
		static::$_db_manager = $manager;
	}
}
