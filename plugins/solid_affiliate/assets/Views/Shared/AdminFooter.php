<?php

namespace SolidAffiliate\Views\Shared;

class AdminFooter
{

    /**
     * @return string
     */
    public static function get_plugin_version()
    {
        $plugin_data = get_plugin_data( SOLID_AFFILIATE_FILE );
        $plugin_version = $plugin_data['Version'];
        return $plugin_version;
    }

   /**
    * Undocumented function
    * 
    * @return string
    */
   public static function render()
   {
      ob_start();
?>
      <style>
         .solid-affiliate-admin-footer {
            border-width: 1px 0 0;
            border-style: solid;
            border-color: #d2d2d2;
            margin: 40px -20px 20px;
            display: flex;
            padding: 20px 20px 10px;
         }

         .solid-affiliate-copyright {
            color: rgba(0,0,0,.6);
            font-size:12px;
         }

         .solid-affiliate-left-container {
            display: inline-block;
         }


         .solid-affiliate-right-container {
            display: inline-block;
            margin-left: auto;
         }

         .solid-affiliate-footer-link {
            display: inline-block;
            margin-left: 25px;
            margin-top: 10px;
         }
      </style>
      <div class="solid-affiliate-admin-footer">
         <div class="solid-affiliate-left-container">
            <div class="solid-affiliate-copyright">
            Made with ❤️ by <a href="https://solidplugins.com" style="color: inherit;" target="_blank" rel="noopener noreferrer">Solid Plugins</a> <span style="font-family:var(--monospace); font-size:11px;">(Version <?php echo self::get_plugin_version(); ?>)<span>
            </div>
         </div>
         <div class="solid-affiliate-right-container">
            <a href="https://solidaffiliate.com/changelog" class="solid-affiliate-footer-link"><?php _e('Changelog', 'solid-affiliate') ?></a>
            <a href="https://docs.solidaffiliate.com/" class="solid-affiliate-footer-link"><?php _e('Documentation', 'solid-affiliate') ?></a>
            <a href="https://solidaffiliate.com/support" class="solid-affiliate-footer-link"><?php _e('Support', 'solid-affiliate') ?></a>
         </div>
      </div>
<?php
      return ob_get_clean();
   }
}
