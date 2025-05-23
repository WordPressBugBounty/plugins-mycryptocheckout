<?php

namespace plainview\sdk_mcc\wordpress\form2;

require_once( 'inputs/class.button.php' );
require_once( 'inputs/class.primary_button.php' );
require_once( 'inputs/class.secondary_button.php' );

/**
	@brief		Wordpress-centric form2.

	@details

	Changelog
	---------

	- 20140203		display_form_table() displays the correct label (legend) for fieldsets.
	- 20131006		display_form_table() reacts to inputfieldsets.
	- 20130604		display_form_table() displays asterisks for required inputs.
	- 20130416		Initial version.
**/
class form
	extends \plainview\sdk_mcc\form2\form
{
	public $base;

	/**
		@brief		The nonce input used to control XHR.
		@since		2024-05-11 20:59:53
	**/
	public $nonce_input = -1;

	public function __construct( $base )
	{
		parent::__construct();
		$this->base = $base;

		// Automatically make the form multipart for FILES access.
		$this->enctype( 'multipart/form-data' );

		// Normally the form assumes a very nicely formatted string with correct scheme and non-standard port detection. This breaks certain non-standard setups. Easiest to just use whatever scheme we're currently using.
		$this->set_attribute( 'action', esc_url( remove_query_arg( 'non_existent_query' ) ) );

		foreach( array(
			'primary_button',
			'secondary_button',
			'wp_editor',
		) as $input )
		{
			$o = new \stdClass();
			$o->name = $input;
			$o->class = sprintf( '\\plainview\\sdk_mcc\\wordpress\form2\\inputs\\%s', $input );
			$this->register_input_type( $o );
		}
	}

	/**
		@brief		Displays an array of inputs using Wordpress table formatting.
		@param		array		$o	Array of options.
		@since		20130416
	**/
	public function display_form_table( $o = array() )
	{
		$o = \plainview\sdk_mcc\base::merge_objects( array(
			'base' => $this->base,
			'header' => '',
			'header_level' => 'h3',
			'inputs' => $this->inputs,
			'r' => '',					// Return value.
		), $o );

		$r = '';

		$this->display_form_table_inputs( $o );

		return $o->r;
	}

	public function display_form_table_inputs( $o )
	{
		if ( $o->header != '' )
			$o->r .= sprintf( '<%s class="title">%s</%s>%s',
				$o->header_level,
				$o->header,
				$o->header_level,
				"\n"
			);

		$o->table = $this->base->table()->set_attribute( 'class', 'form-table' );

		foreach( $o->inputs as $input )
		{
			// Input containers (fieldsets) must be recursed.
			$container = false;
			$container |= is_subclass_of( $input, 'plainview\\sdk_mcc\\form2\\inputs\\inputfieldset' );
			$uses = class_uses( $input );
			$container |= isset( $uses[ 'plainview\\sdk_mcc\\form2\\inputs\\traits\\container' ] );

			if ( $container )
			{
				// Should the table be displayed?
				if ( count( $o->table->body->rows ) > 0 )
					$o->r .= $o->table;

				// Clone the options object to allow the input container to create its own table
				$o2 = clone $o;
				$o2->header = $input->label;

				if ( $input->label->content != '' )
					$o2->header = $input->label->content;
				else
					// If this is a container with a legend (fieldset) use the legend.
					$o2->header = $input->legend->label->content;

				$o2->inputs = $input->inputs;
				$o2->r = '';
				$o2->table = $this->base->table()->set_attribute( 'class', 'form-table' );
				$this->display_form_table_inputs( $o2 );

				$o->table = $this->base->table()->set_attribute( 'class', 'form-table' );
				$o->r .= sprintf( '<div class="fieldset fieldset_%s">%s</div>',
					$input->get_name(),
					$o2->r
				);
				continue;
			}

			// Hidden inputs cannot be displayed.
			if ( $input->get_attribute( 'hidden' ) )
			{
				$o->r .= $input->display_input();
				continue;
			}

			if ( is_a( $input, 'plainview\\sdk_mcc\\form2\\inputs\\markup' ) )
			{
				$o->table->body()->row()->td()->set_attribute( 'colspan', 2 )->text( $input->display_input() );
				continue;
			}

			$description = $input->display_description( $input );
			if ( $description != '' )
				$description = sprintf( '<div class="input_description">%s</div>', $description );
			$row = $o->table->body()->row();

			if ( ! $input->validates() )
				$row->css_class( 'does_not_validate' );

			$label = $input->display_label();
			if ( $input->is_required() )
				$label .= sprintf( ' <sup><abbr title="">*</abbr></sup>',
					$this->base->_( 'This input is required.' )
				);
			$row->th()->text( $label )->row()
				->td()->textf( '<div class="input_itself">%s</div>%s',
					$input->display_input( $input ),
					$description
				);
		}
		if ( count( $o->table->body->rows ) > 0 )
			$o->r .= $o->table;
	}

	/**
		@brief		Ask base to translate this string. Is sprintf aware.
		@param		string		$string		The string to translate.
		@see		\\plainview\\sdk_mcc\\form2\\form::_
		@return		string					The translated string.
	**/

	public function _( $string )
	{
		return call_user_func_array( array( $this->base, '_' ), func_get_args() );
	}

	/**
		@brief		Generate a nonce based on the ID.
		@since		2023-09-06 06:39:34
	**/
	public function generate_nonce()
	{
		$nonce_key = $this->get_nonce_key();
		$nonce = wp_create_nonce( $nonce_key );
		$this->nonce_input = $this->hidden_input( $nonce_key )
			->value( $nonce );
		return $this;
	}

	/**
		@brief		Return the key used for the nonce.
		@since		2023-09-06 06:30:51
	**/
	public function get_nonce_key()
	{
		$form_id = $this->get_attribute( 'id' );
		$nonce_key = 'automatic_nonce_' . $form_id;
		return $nonce_key;
	}

	/**
		@brief		Create a nonce together with the ID.
		@since		2023-09-06 06:13:58
	**/
	public function id( $new_id )
	{
		parent::id( $new_id );
		$this->generate_nonce();
	}

	/**
		@brief		Check that the nonce exists, otherwise the form is not being posted.
		@details	Created to prevent a conflict of POSTs when two forms are on the same page.
		@since		2024-02-13 17:24:49
	**/
	public function is_posting( array $post = null )
	{
		if ( is_object( $this->nonce_input ) )
		{
			$nonce_key = $this->get_nonce_key();
			if ( ! isset( $_POST[ $nonce_key ] ) )
				return false;
		}
		return parent::is_posting( $post );
	}

	/**
		@brief		Remove the automatic nonce from the form, for the sake of ajax requests.
		@since		2023-09-18 11:56:18
	**/
	public function no_automatic_nonce()
	{
		$this->nonce_input = -1;
		return $this;
	}

	/**
		@brief		Automatically check the nonce.
		@since		2023-09-06 06:16:45
	**/
	public function post( array $post = null )
	{
		parent::post( $post );
		if ( ! is_object( $this->nonce_input ) )
			return $this;
		$the_nonce = $this->nonce_input->get_post_value();

		$nonce_key = $this->get_nonce_key();

		if ( ! wp_verify_nonce( $the_nonce, $nonce_key ) )
			wp_nonce_ays( 'Form validity check failed (missing token).' );

		return $this;
	}

	public function start()
	{
		return $this->open_tag();
	}

	public function stop()
	{
		return $this->close_tag();
	}
}

