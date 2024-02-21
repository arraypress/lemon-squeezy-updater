<tr class="lemon-squeezy-updater-license-row <?php esc_attr_e( $this->get_license_status_class() ); ?>">
    <td colspan="5">
        <div class="lemon-squeezy-updater-license-wrap">
            <label><?php esc_html_e( 'License:', 'arraypress' ); ?>&nbsp;
                <input type="text" value="<?php esc_attr_e( $this->get_license_key() ); ?>" class="regular-text" placeholder="<?php esc_attr_e( 'Your license key', 'arraypress' ); ?>"/>
            </label>
            <span class="spinner"></span>
			<?php echo wp_kses_post( $this->get_license_message() ); ?>
        </div>
    </td>
</tr>