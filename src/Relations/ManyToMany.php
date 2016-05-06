<?php
/**
 * ManyToMany relation.
 *
 * @author    Iron Bound Designs
 * @since     2.0
 * @license   MIT
 * @copyright Iron Bound Designs, 2016.
 */

namespace IronBound\DB\Relations;

use Doctrine\Common\Collections\Collection;
use IronBound\DB\Collections\ModelCollection;
use IronBound\DB\Model;
use IronBound\DB\Query\FluentQuery;
use IronBound\DB\Query\Tag\Where;
use IronBound\DB\Table\AssociationTable;
use IronBound\DB\Table\Table;
use IronBound\WPEvents\GenericEvent;

/**
 * Class ManyToMany
 * @package IronBound\DB\Relations
 */
class ManyToMany extends Relation {

	/**
	 * @var AssociationTable
	 */
	protected $association;

	/**
	 * @var string
	 */
	protected $other_column;

	/**
	 * @var string
	 */
	protected $primary_column;

	/**
	 * @var string
	 */
	protected $attribute;

	/**
	 * @var string
	 */
	protected $other_attribute;

	/**
	 * ManyToMany constructor.
	 *
	 * @param string           $related Related class name.
	 * @param Model            $parent  Parent object.
	 * @param AssociationTable $association
	 * @param string           $attribute
	 * @param string           $other_attribute
	 */
	public function __construct( $related, Model $parent, AssociationTable $association, $attribute, $other_attribute ) {
		parent::__construct( $related, $parent );

		$this->association = $association;

		/** @var Table $related_table */
		$related_table = $related::table();

		if ( $related_table->get_slug() === $association->get_table_a()->get_slug() ) {
			$this->other_column   = $association->get_col_b();
			$this->primary_column = $association->get_col_a();
		} else {
			$this->other_column   = $association->get_col_a();
			$this->primary_column = $association->get_col_b();
		}

		$this->attribute       = $attribute;
		$this->other_attribute = $other_attribute;
	}

	/**
	 * @inheritDoc
	 */
	protected function fetch_results() {

		/** @var FluentQuery $query */
		$query = call_user_func( array( $this->related_model, 'query' ) );
		$query->distinct();

		$related = $this->related_model;
		$parent  = $this->parent;
		$column  = $this->other_column;

		$query->join( $this->association, $related::table()->get_primary_key(), $this->primary_column, '=',
			function ( FluentQuery $query ) use ( $parent, $column ) {
				$query->where( $column, true, $parent->get_pk() );
			} );

		$results = $query->results();
		$results->keep_memory();

		return $results;
	}

	/**
	 * @inheritDoc
	 */
	protected function register_events() {

		$related = $this->related_model;

		$self            = $this;
		$parent          = $this->parent;
		$results         = $this->results;
		$attribute       = $this->attribute;
		$other_attribute = $this->other_attribute;

		// the parent is a movie relation

		// Whenever an actor is saved, I want to check for the movies that have been added to this actor
		// check if the parent actor is contained within any of the movies related actors
		// and if so add those movies to this actor

		// whenever a movie is saved
		$parent::saved( function ( GenericEvent $event ) use ( $results, $attribute, $other_attribute ) {

			return;

			/** @var Model $model */
			$model = $event->get_subject();

			if ( ! $model->is_relation_loaded( $attribute ) ) {
				return;
			}

			// these are actor objects

			/** @var ModelCollection $relation */
			$relation = $model->get_attribute( $attribute );

			/** @var Model $added */
			foreach ( $relation->get_added() as $added ) {

			}

			/** @var Model $related */
			foreach ( $relation as $related ) {

				if ( ! $related->is_relation_loaded( $other_attribute ) ) {
					continue;
				}

			}

		} );

		// whenever a actor is saved
		$related::saved( function ( GenericEvent $event ) use ( $parent, $results, $attribute, $other_attribute ) {

			/** @var Model $model */
			$model = $event->get_subject();

			if ( ! $model->is_relation_loaded( $other_attribute ) ) {
				return;
			}

			// these are movie objects

			/** @var ModelCollection $relation */
			$relation = $model->get_attribute( $other_attribute );

			$added = $relation->get_added();

			if ( isset( $added[ $parent->get_pk() ] ) ) {
				$results->dont_remember( function ( ModelCollection $collection ) use ( $model ) {
					$collection->add( $model );
				} );
			}

			$removed = $relation->get_removed();

			if ( isset( $removed[ $parent->get_pk() ] ) ) {
				$results->dont_remember( function ( ModelCollection $collection ) use ( $model ) {
					$collection->remove( $model->get_pk() );
				} );
			}

		} );

		$related::deleted( function ( GenericEvent $event ) use ( $self, $results ) {
			$results->remove( $event->get_subject()->get_pk() );
		} );
	}

	/**
	 * @inheritDoc
	 */
	public function model_matches_relation( Model $model ) {

		$query = new FluentQuery( $GLOBALS['wpdb'], $this->association );
		$query->where( $this->primary_column, true, $this->parent->get_pk() );
		$query->and_where( $this->other_column, true, $model->get_pk() );

		return ! is_null( $query->first() );
	}

	/**
	 * Fetch results for eager loading.
	 *
	 * @since 2.0
	 *
	 * @param Model[]  $models
	 * @param callable $callback
	 *
	 * @return Collection
	 */
	protected function fetch_results_for_eager_load( array $models, $callback = null ) {

		$related      = $this->related_model;
		$other_column = $this->other_column;

		$query = new FluentQuery( $GLOBALS['wpdb'], $related::table() );
		$query->distinct();
		$query->select_all( false );

		$query->join( $this->association, $related::table()->get_primary_key(), $this->primary_column, '=',
			function ( FluentQuery $query ) use ( $other_column, $models ) {
				$query->where( $other_column, true, array_keys( $models ) );
			}, 'LEFT' );

		if ( $callback ) {
			$callback( $query );
		}

		return $query->results();
	}

	/**
	 * @inheritDoc
	 */
	public function eager_load( array $models, $attribute, $callback = null ) {

		$results = $this->fetch_results_for_eager_load( $models, $callback );
		$memory  = (bool) $this->keep_synced;
		$related = array();

		$relationship_map = array();

		foreach ( $results as $result ) {

			$attributes = $result;
			unset( $attributes[ $this->primary_column ] );
			unset( $attributes[ $this->other_column ] );

			$model = call_user_func( array( $this->related_model, 'from_query' ), $attributes );

			$related[ $model->get_pk() ] = $model;

			$relationship_map[ $result[ $this->other_column ] ][ $model->get_pk() ] = $model;
		}

		foreach ( $models as $model ) {

			if ( isset( $relationship_map[ $model->get_pk() ] ) ) {
				$model->set_relation_value( $attribute, new ModelCollection( $relationship_map[ $model->get_pk() ], $memory ) );
			} else {
				$model->set_relation_value( $attribute, new ModelCollection( array(), $memory ) );
			}
		}
	}

	/**
	 * @inheritdoc
	 */
	public function persist( $values ) {

		/** @var \wpdb $wpdb */
		global $wpdb;

		if ( $this->parent->get_pk() && $values->get_removed() ) {

			$where = new Where( 1, true, 1 );

			foreach ( $values->get_removed() as $removed ) {

				$remove_where = new Where( $this->other_column, true, $this->parent->get_pk() );
				$remove_where->qAnd( new Where( $this->primary_column, true, $removed->get_pk() ) );

				$where->qOr( $remove_where );
			}

			$wpdb->query( "DELETE FROM `{$this->association->get_table_name( $wpdb )}` $where" );
		}

		$insert = array();

		foreach ( $values as $value ) {
			// prevent recursion
			$value->save( array( 'exclude_relations' => $this->other_attribute ) );
		}

		foreach ( $values->get_added() as $added ) {
			$insert[] = "({$this->parent->get_pk()},{$added->get_pk()})";
		}

		if ( empty( $insert ) ) {
			return;
		}

		$insert = implode( ',', $insert );

		$sql = "INSERT IGNORE INTO `{$this->association->get_table_name( $wpdb )}` ";
		$sql .= "({$this->other_column},{$this->primary_column}) VALUES $insert";

		$wpdb->query( $sql );
	}

	/**
	 * @inheritDoc
	 */
	public function on_delete( Model $model ) {

		/** @var \wpdb $wpdb */
		global $wpdb;

		$wpdb->delete( $this->association->get_table_name( $wpdb ), array(
			$this->other_column => $model->get_pk()
		) );
	}
}