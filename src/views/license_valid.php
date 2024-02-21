<tr class="lemon-squeezy-updater-license-row <?php esc_attr_e( $this->get_license_status_class() ); ?>">
    <td colspan="5">
        <div class="lemon-squeezy-updater-license-wrap">
			<?php echo wp_kses_post( $this->get_license_message() ); ?>
            <a href="javascript:void(0);" class="deactivate"><?php _e( 'Deactivate license' ); ?></a>
            <span class="spinner"></span>
        </div>
    </td>
</tr>