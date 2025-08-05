<?php


namespace SolidAffiliate\Views\Shared\Charts;

use SolidAffiliate\Lib\ChartData;
use SolidAffiliate\Lib\Integrations\WooCommerceIntegration;
use SolidAffiliate\Lib\RandomData;
use SolidAffiliate\Lib\VO\PresetDateRangeParams;

class SolidChartViewTwo
{
    /**
     * @param PresetDateRangeParams $date_range
     * 
     * @return string
     */
    public static function render($date_range)
    {
        $random_id = 'solid-affiliate-chart-' . RandomData::string();

        ob_start();
?>
        <div class='sld_chart-container'>
            <div class="sld_chart-container_header">
                <h2><?php _e('Affiliate Orders and Commissions (monthly)', 'solid-affiliate') ?></h2>
            </div>
            <canvas id="<?php echo ($random_id) ?>"></canvas>
        </div>
        <script>
            var ctx = document.getElementById('<?php echo ($random_id) ?>');
            var myChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    datasets: [{
                            label: "<?php _e('Total Order Amount', 'solid-affiliate') ?>",
                            data: <?php echo (json_encode(ChartData::referrals_data('monthly', $date_range))) ?>,
                            parsing: {
                                yAxisKey: 'total_order_amount'
                            },
                            backgroundColor: 'rgba(153, 102, 255, 0.3)',
                            borderColor: 'rgb(153, 102, 255)',
                            borderWidth: 1
                        },
                        {
                            label: "<?php _e('Total Commission Amount', 'solid-affiliate') ?>",
                            data: <?php echo (json_encode(ChartData::referrals_data('monthly', $date_range))) ?>,
                            parsing: {
                                yAxisKey: 'total_commission_amount'
                            },
                            backgroundColor: 'rgba(255, 159, 64, 1)',
                            borderColor: 'rgb(255, 159, 64)',
                            borderWidth: 1
                        },
                    ]
                },
                options: {
                    parsing: {
                        xAxisKey: 'date',
                    },
                    scales: {
                        x: {
                            type: 'time',
                            title: 'Date',
                            stacked: true,
                            time: {
                                unit: 'month',
                                tooltipFormat: 'do MMM Y'
                            }
                        },
                        y: {
                            beginAtZero: true,
                            ticks: {
                                // Include a dollar sign in the ticks
                                callback: function(value, index, values) {
                                    return SolidAffiliateAdmin.format_money(value);
                                }
                            }
                        }
                    }
                }
            });
        </script>
<?php
        return ob_get_clean();
    }
}
