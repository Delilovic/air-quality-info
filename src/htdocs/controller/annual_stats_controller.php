<?php
namespace AirQualityInfo\Controller;

class AnnualStatsController extends AbstractController {

    private $recordModel;

    public function __construct(\AirQualityInfo\Model\RecordModel $recordModel) {
        $this->recordModel = $recordModel;
    }

    public function index($device) {
        $dailyAvgs = $this->recordModel->getDailyAverages($device['id']);
        $daysWithHighPm10 = 0;
        $averages = ['pm10' => 0, 'pm25' => 0];

        foreach ($dailyAvgs as $dailyAvg) {
            if ($dailyAvg['pm10_avg'] > 50) {
                $daysWithHighPm10++;
            }
            $averages['pm10'] += $dailyAvg['pm10_avg'];
            $averages['pm25'] += $dailyAvg['pm25_avg'];
        }
        $averages['pm10'] /= count($dailyAvgs);
        $averages['pm25'] /= count($dailyAvgs);

        $nodeById = $this->deviceHierarchyModel->getAllNodesById($this->userId);
        $path = \AirQualityInfo\Model\DeviceHierarchyModel::calculateDevicePath($nodeById, $device['id']);

        $this->render(array('view' => 'views/annual_stats.php'), array(
            'averages' => $averages,
            'device' => $device,
            'daysWithHighPm10' => $daysWithHighPm10,
            'breadcrumbs' => $path
        ));
    }

    public function get_data($device) {
        $averages = $this->recordModel->getDailyAverages($device['id']);

        $pm10_days_by_levels = array(0, 0, 0, 0, 0);
        $pm25_days_by_levels = array(0, 0, 0, 0, 0);

        foreach ($averages as $day) {
            if ($day['pm10_avg'] !== null) {
                $pm10_level = \AirQualityInfo\Lib\PollutionLevel::findLevel(\AirQualityInfo\Lib\PollutionLevel::PM10_THRESHOLDS_24H, $day['pm10_avg']);
                $pm10_days_by_levels[$pm10_level]++;
            }

            if ($day['pm25_avg'] !== null) {
                $pm25_level = \AirQualityInfo\Lib\PollutionLevel::findLevel(\AirQualityInfo\Lib\PollutionLevel::PM25_THRESHOLDS_24H, $day['pm25_avg']);
                $pm25_days_by_levels[$pm25_level]++;
            }
        }

        $data = array();
        $data['pm10']['days_by_levels'] = $pm10_days_by_levels;
        $data['pm25']['days_by_levels'] = $pm25_days_by_levels;
        $data['pm10']['levels'] = \AirQualityInfo\Lib\PollutionLevel::PM10_THRESHOLDS_24H;
        $data['pm25']['levels'] = \AirQualityInfo\Lib\PollutionLevel::PM25_THRESHOLDS_24H;
        $data['level_names'] = array_map(function($e) { return $e['name']; }, \AirQualityInfo\Lib\PollutionLevel::POLLUTION_LEVELS);
        
        header('Content-type:application/json;charset=utf-8');
        echo json_encode($data);
    }
}
?>