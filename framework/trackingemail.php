<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
/**
 * A custom Order WooCommerce Email class
 * Copyright (c) 2013-2022 Jamez Picard
 *
 * @extends \WC_Email
 */
abstract class cpwebservice_trackingemail extends WC_Email {
    /**
     * Email setup
     */

    public $tracking_delivered = false;
    public $tracking_shipped = false;

    public function __construct() {

        $this->id = 'cpwebservice_tracking_order';
        $this->customer_email = true;
        // Title in WooCommerce Email settings
        $this->title = $this->get_resource('method_title') .'  '. __('Order tracking', 'woocommerce-canadapost-webservice');

        // Description in WooCommerce email settings
        $this->description = $this->get_resource('method_title') . '  '.__('Tracking Order Notification emails are sent when tracking events are updated (Shipped and Delivered).', 'woocommerce-canadapost-webservice');
        $this->placeholders   = array(
            '{order_date}'   => '',
            '{order_number}' => '',
        );
        // Default heading and subject lines that can be overridden using the settings
        $this->heading = $this->get_default_heading();
        $this->subject = $this->get_default_subject();

        // Locations of the templates that this email should use
        $this->template_html  = 'emails/customer-invoice.php';
        $this->template_plain = 'emails/plain/customer-invoice.php';

        // Call parent constructor
        parent::__construct();
    }

    /*
	 * Return resources
	 */
	abstract function get_resource($id);

    /**
     * Default content to show below main email content.
     *
     * @since 3.7.0
     * @return string
     */
    public function get_default_additional_content() {
        return '';
    }

    /**
     * Determine if the email should be sent and setup email merge variables
     *
     * @param int $order_id
     * @param WC_Order $order
     */
    public function trigger( $order_id, $order = false ) {

        $this->setup_locale();

        if ( $order_id && ! is_a( $order, 'WC_Order' ) ) {
            $order = wc_get_order( $order_id );
        }

        if ( is_a( $order, 'WC_Order' ) ) {
            $this->object                         = $order;
            $this->recipient                      = $this->object->get_billing_email();
            $this->placeholders['{order_date}']   = wc_format_datetime( $this->object->get_date_created() );
            $this->placeholders['{order_number}'] = $this->object->get_order_number();
        }

        // Check if there is tracking data for this order. Do not send notification if no data.
        $db = new woocommerce_cpwebservice_db();
        $tracking_list = $db->tracking_get($order_id);
        if (empty($tracking_list)){
            return;
        }
        
        // Order Events:  Shipped or Delivered (Full or Partial)
        foreach($tracking_list as $tracking){
            // Delivered event has happened within 48 hrs.
            if (!empty($tracking->datedelivered) && (time() - strtotime($tracking->datedelivered) < DAY_IN_SECONDS * 2))
            {
                $this->tracking_delivered = true;
            }
            // Shipped event has happened within 48 hrs.
            if (!empty($tracking->dateshipped) && (time() - strtotime($tracking->dateshipped) < DAY_IN_SECONDS * 2))
            {
                $this->tracking_shipped = true;
            }
        }

        if ( $this->get_recipient() && $this->is_enabled()) {
            $this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );

            // Add note to indicate email has been sent to customer.
            $note = __( 'Sent email notification', 'woocommerce-canadapost-webservice' ) . ': ' . $this->get_subject();
            $this->object->add_order_note($note);
        }

        $this->restore_locale();
    }

    /**
     * Get content html.
     *
     * @return string
     */
    public function get_content_html() {
        return wc_get_template_html(
            $this->template_html,
            array(
                'order'              => $this->object,
                'email_heading'      => $this->get_heading(),
                'additional_content' => $this->get_additional_content(),
                'sent_to_admin'      => false,
                'plain_text'         => false,
                'email'              => $this,
            )
        );
    }

    /**
     * Get content plain.
     *
     * @return string
     */
    public function get_content_plain() {
        return wc_get_template_html(
            $this->template_plain,
            array(
                'order'              => $this->object,
                'email_heading'      => $this->get_heading(),
                'additional_content' => $this->get_additional_content(),
                'sent_to_admin'      => false,
                'plain_text'         => true,
                'email'              => $this,
            )
        );
    }
        /**
		 * Get default email subject.
		 *
		 * @param bool $delivered Whether the tracked package has been delivered or not.
		 * @return string
		 */
		public function get_default_subject( $event = '' ) {
			if ( $event == 'delivered' ) {
				return __( 'Your Order has been Delivered #{order_number} from {site_title}', 'woocommerce-canadapost-webservice' );
            } elseif ( $event == 'shipped' ) {
                return __( 'Your Order has Shipped #{order_number} from {site_title}', 'woocommerce-canadapost-webservice' );
			} else {
				return __( 'Shipping update for order #{order_number} from {site_title}', 'woocommerce-canadapost-webservice' );
			}
		}

		/**
		 * Get email heading.
		 *
		 * @param bool $delivered Whether the tracked package has been delivered or not.
		 * @return string
		 */
		public function get_default_heading( $event = '' ) {
			if ( $event == 'delivered' ) {
				return __( 'Order #{order_number} has been Delivered', 'woocommerce-canadapost-webservice' );
			} elseif ( $event == 'shipped' ) {
				return __( 'Order Shipped! Track your Order #{order_number}', 'woocommerce-canadapost-webservice' );
			} else {
				return __( 'Shipping update for order #{order_number}', 'woocommerce-canadapost-webservice' );
			}
		}

        /**
		 * Get email subject.
		 *
		 * @return string
		 */
		public function get_subject() {
			if ( $this->tracking_delivered ) {
				$subject = $this->get_option('delivered_subject', $this->get_default_subject( 'delivered' ) );
                return $this->format_string($subject);
            } elseif ( $this->tracking_shipped ) {
                $subject = $this->get_option('shipped_subject', $this->get_default_subject('shipped'));
                return $this->format_string($subject);
            }
            $subject = $this->get_option('subject', $this->get_default_subject());
            return $this->format_string($subject);
		}

		/**
		 * Get email heading.
		 *
		 * @return string
		 */
		public function get_heading() {
			if ( $this->tracking_delivered ) {
				$heading = $this->get_option( 'delivered_heading', $this->get_default_heading('delivered') );
                return $this->format_string($heading);
            } elseif ( $this->tracking_shipped ) {
                $heading = $this->get_option( 'shipped_heading', $this->get_default_heading('shipped') );
			    return $this->format_string($heading);
			}
			$heading = $this->get_option( 'heading', $this->get_default_heading() );
			return $this->format_string($heading);
		}


    /**
     * Initialise settings form fields.
     */
    public function init_form_fields() {
        /* translators: %s: list of placeholders */
        $placeholder_text  = sprintf( __( 'Available placeholders: %s', 'woocommerce' ), '<code>' . esc_html( implode( '</code>, <code>', array_keys( $this->placeholders ) ) ) . '</code>' );
        $this->form_fields = array(
            'enabled'    => array(
                'title'   => 'Enable/Disable',
                'type'    => 'checkbox',
                'label'   => 'Enable this email notification',
                'description' => (esc_html(__( 'Make sure to enable automatic tracking emails', 'woocommerce-canadapost-webservice' )) . ': ' . '<a href="admin.php?page=wc-settings&tab=shipping&section=woocommerce_cpwebservice#cpwebservice_tracking_panel" target="_blank">' . esc_html($this->get_resource('method_title') . ' ' . __('Tracking Settings', 'woocommerce-canadapost-webservice')) . '</a>'),
                'default' => 'yes'
            ),
            'subject'       => array(
                'title'       => __( '(Default): Subject', 'woocommerce-canadapost-webservice' ),
                'type'        => 'text',
                'desc_tip'    => true,
                'description' => $placeholder_text,
                'placeholder' => $this->get_default_subject(),
                'default'     => '',
            ),
            'heading'       => array(
                'title'       => __( '(Default): Email heading', 'woocommerce-canadapost-webservice' ),
                'type'        => 'text',
                'desc_tip'    => true,
                'description' => $placeholder_text,
                'placeholder' => $this->get_default_heading(),
                'default'     => '',
            ),
            'shipped_subject'       => array(
                'title'       => __( 'Shipped: Subject', 'woocommerce-canadapost-webservice' ),
                'type'        => 'text',
                'desc_tip'    => true,
                'description' => $placeholder_text,
                'placeholder' => $this->get_default_subject('shipped'),
                'default'     => '',
            ),
            'shipped_heading'       => array(
                'title'       => __( 'Shipped: Email heading', 'woocommerce-canadapost-webservice' ),
                'type'        => 'text',
                'desc_tip'    => true,
                'description' => $placeholder_text,
                'placeholder' => $this->get_default_heading('shipped'),
                'default'     => '',
            ),
            'delivered_subject' => array(
                'title'       => __( 'Delivered: Subject', 'woocommerce-canadapost-webservice' ),
                'type'        => 'text',
                'desc_tip'    => true,
                'description' => $placeholder_text,
                'placeholder' => $this->get_default_subject('delivered'),
                'default'     => '',
            ),
            'delivered_heading'  => array(
                'title'       => __( 'Delivered: Email heading', 'woocommerce-canadapost-webservice' ),
                'type'        => 'text',
                'desc_tip'    => true,
                'description' => $placeholder_text,
                'placeholder' => $this->get_default_heading('delivered'),
                'default'     => '',
            ),
            'additional_content' => array(
                'title'       => __( 'Additional content', 'woocommerce' ),
                'description' => __( 'Text to appear below the main email content.', 'woocommerce' ) . ' ' . $placeholder_text,
                'css'         => 'width:400px; height: 75px;',
                'placeholder' => '',
                'type'        => 'textarea',
                'default'     => $this->get_default_additional_content(),
                'desc_tip'    => true,
            ),
            'email_type'         => array(
                'title'       => __( 'Email type', 'woocommerce' ),
                'type'        => 'select',
                'description' => __( 'Choose which format of email to send.', 'woocommerce' ),
                'default'     => 'html',
                'class'       => 'email_type wc-enhanced-select',
                'options'     => $this->get_email_type_options(),
                'desc_tip'    => true,
            )
        );
    }

} // end class