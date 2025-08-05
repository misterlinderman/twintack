<?php


namespace SolidAffiliate\Views\Shared\Charts;

use SolidAffiliate\Lib\ChartData;
use SolidAffiliate\Lib\RandomData;
use SolidAffiliate\Lib\VO\PresetDateRangeParams;

class SolidChartViewSix
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
                <h2><?php _e('Visits (monthly)', 'solid-affiliate') ?></h2>
            </div>
            <canvas id="<?php echo ($random_id) ?>"></canvas>
        </div>
        <script>
            var ctx = document.getElementById('<?php echo ($random_id) ?>');
            var myChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    datasets: [{
                            label: "<?php _e('Total Visits', 'solid-affiliate') ?>",
                            data: <?php echo (json_encode(ChartData::visits_data('monthly', $date_range))) ?>,
                            parsing: {
                                yAxisKey: 'count'
                            },
                            backgroundColor: 'rgba(255, 205, 86, 0.3)',
                            borderColor: 'rgb(255, 205, 86)',
                            borderWidth: 1
                        },
                        {
                            label: "<?php _e('Total Converted Visits', 'solid-affiliate') ?>",
                            data: <?php echo (json_encode(ChartData::visits_data('monthly', $date_range))) ?>,
                            parsing: {
                                yAxisKey: 'converted_count'
                            },
                            backgroundColor: 'rgba(103, 202, 255, 0.7)',
                            borderColor: 'rgb(103, 202, 255)',
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
                            ticks: {}
                        }
                    }
                }
            });
        </script>
<?php
        return ob_get_clean();
    }
}
