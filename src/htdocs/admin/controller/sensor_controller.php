<?php
namespace AirQualityInfo\Admin\Controller;

class SensorController extends AbstractController {

    private $deviceModel;

    private $deviceHierarchyModel;

    private $sensorCommunityApi;

    private $recordModel;

    public function __construct(
            \AirQualityInfo\Model\DeviceModel $deviceModel,
            \AirQualityInfo\Model\DeviceHierarchyModel $deviceHierarchyModel,
            \AirQualityInfo\Model\RecordModel $recordModel,
            \AirQualityInfo\Lib\SensorCommunityApi $sensorCommunityApi) {
        $this->deviceModel = $deviceModel;
        $this->deviceHierarchyModel = $deviceHierarchyModel;
        $this->sensorCommunityApi = $sensorCommunityApi;
        $this->recordModel = $recordModel;
        $this->title = __('Devices');
    }

    public function map() {
        header('Content-type: application/json');
        readfile("https://maps.sensor.community/data/v2/data.dust.min.json");
    }

    public function create() {
        $deviceForm = new \AirQualityInfo\Lib\Form\Form("deviceForm");
        $deviceForm->addElement('sensor_id', 'text', 'Sensor id')->addRule('required');
        $this->addNameField($deviceForm)
            ->setOptions(array('prepend' => 'https://' . $this->user['domain'] . CONFIG['user_domain_suffixes'][0] . '/'));
        $deviceForm->addElement('description', 'text', 'Description')->addRule('required');
        if ($deviceForm->isSubmitted() && $deviceForm->validate($_POST)) {
            $sensorId = $_POST['sensor_id'];
            $matching = $this->sensorCommunityApi->getMatchingSensors(array($sensorId));
            list($matchingSensors, $location) = $matching[$sensorId];
            $deviceId = $this->deviceModel->createDevice(array(
                'user_id' => $this->user['id'],
                'name' => $_POST['name'],
                'description' => $_POST['description'],
                'update_mode' => 'pull',
                'default_device' => 0,
                'location_provided' => 1,
                'lat' => $location['latitude'],
                'lng' => $location['longitude'],
                'elevation' => $location['altitude']
            ));
            foreach ($matchingSensors as $sensorId) {
                $this->deviceModel->insertSensor($deviceId, $sensorId);
            }
            $rootId = $this->deviceHierarchyModel->getRootId($this->user['id']);
            $this->deviceHierarchyModel->addChild($this->user['id'], $rootId, null, null, $deviceId);
            
            $this->alert(__('Linked device', 'success'));
            header('Location: '.l('device', 'edit', null, array('device_id' => $deviceId)));
        } else {
            $this->render(array(
                'view' => 'admin/views/sensor/create.php'
            ), array(
                'deviceForm' => $deviceForm
            ));
        }
    }

    public function createCustom() {
        $deviceForm = new \AirQualityInfo\Lib\Form\Form("deviceForm");
        $this->addNameField($deviceForm)
            ->setOptions(array('prepend' => 'https://' . $this->user['domain'] . CONFIG['user_domain_suffixes'][0] . '/'));
        $deviceForm->addElement('description', 'text', 'Description')->addRule('required');
        if ($deviceForm->isSubmitted() && $deviceForm->validate($_POST)) {
            $deviceId = $this->deviceModel->createDevice(array(
                'user_id' => $this->user['id'],
                'name' => $_POST['name'],
                'description' => $_POST['description'],
                'update_mode' => 'pull',
                'default_device' => 0,
            ));

            $rootId = $this->deviceHierarchyModel->getRootId($this->user['id']);
            $this->deviceHierarchyModel->addChild($this->user['id'], $rootId, null, null, $deviceId);
            
            $this->alert(__('Linked device', 'success'));
            header('Location: '.l('device', 'edit', null, array('device_id' => $deviceId)));
        } else {
            $this->render(array(
                'view' => 'admin/views/sensor/create_custom.php'
            ), array(
                'deviceForm' => $deviceForm
            ));
        }
    }

    public function edit($deviceId) {
        $device = $this->getDevice($deviceId);
        $nodes = $this->deviceHierarchyModel->getDeviceNodes($this->user['id'], $device['id']);
        $breadcrumbs = null;
        if (!empty($nodes)) {
            $breadcrumbs = $this->deviceHierarchyModel->getPath($this->user['id'], $nodes[0]);
        }

        $deviceForm = $this->getDeviceForm($device);
        if ($deviceForm->isSubmitted() && $deviceForm->validate($_POST)) {
            $data = array(
                'name' => $_POST['name'],
                'description' => $_POST['description'],
                'extra_description' => $_POST['extra_description'],
                'location_provided' => $_POST['location_provided'],
                'lat' => $_POST['lat'],
                'lng' => $_POST['lng'],
                'radius' => $_POST['radius'],
                'elevation' => empty($_POST['elevation']) ? NULL : $_POST['elevation']
            );
            $this->deviceModel->updateDevice($deviceId, $data);
            $this->alert(__('Updated the device', 'success'));
            $device = $this->getDevice($deviceId);
            $deviceForm->setDefaultValues($device);
        }

        $sensorIdForm = new \AirQualityInfo\Lib\Form\Form("sensorIdForm");
        $sensorIdForm->addElement('sensor_id', 'number', 'Sensor id')->addRule('required');
        $sensorIdForm->addElement('type', 'select', 'Sensor type')
            ->addRule('required')
            ->setOptions(array(
                'sensor.community' => 'sensor.community',
                'smogtok' => 'SmogTok',
                'syngeos' => 'Syngeos',
                'gios' => 'GIOŚ'
            ));
        if ($sensorIdForm->isSubmitted() && $sensorIdForm->validate($_POST)) {
            $this->deviceModel->insertSensor($deviceId, $_POST['sensor_id'], $_POST['type']);
            $this->alert(__('Added new sensor id', 'success'));    
        }

        $sensorIds = $this->deviceModel->getSensorIds($deviceId);

        $this->render(array(
            'view' => 'admin/views/sensor/edit.php'
        ), array(
            'device' => $device,
            'sensorIds' => $sensorIds,
            'deviceId' => $deviceId,
            'deviceForm' => $deviceForm,
            'sensorIdForm' => $sensorIdForm,
            'lastRecord' => $this->recordModel->getLastData($deviceId),
            'breadcrumbs' => $breadcrumbs,
            'lastItemLink' => false
        ));
    }

    public function deleteSensorId($deviceId, $sensorId) {
        $this->getDevice($deviceId); // validate the device ownership
        $this->deviceModel->deleteSensorId($deviceId, $sensorId);
        $this->alert(__('Deleted the sensor id'));
    }

    private function getDevice($deviceId) {
        $device = $this->deviceModel->getDeviceById($deviceId);
        if ($device == null || $device['user_id'] != $this->user['id']) {
            header('Location: '.l('device', 'index'));
            die();
        }
        return $device;
    }

    private function getDeviceForm($device) {
        $deviceForm = new \AirQualityInfo\Lib\Form\Form("deviceForm");
        $this->addNameField($deviceForm);
        $deviceForm->addElement('description', 'text', 'Description')->addRule('required');
        $deviceForm->addElement('extra_description', 'text', 'Extra description');
        $deviceForm->addElement('location_provided', 'checkbox', 'Choose location', array('data-toggle'=>'collapse', 'data-target'=>'.map-control'), null);
        $deviceForm->addElement('radius', 'number', 'Radius (m)', array('min' => 50, 'max' => 500, 'step' => 50))
            ->addGroupClass('map-control')
            ->addGroupClass('collapse')
            ->addRule('required')
            ->addRule('range', array('min' => 50, 'max' => 500, 'message' => 'Please choose value between 50 and 500.' ));
        $deviceForm->addElement('elevation', 'number', 'Elevation (m a.s.l.)', array('min' => -10994, 'max' => 8848))
            ->addGroupClass('map-control')
            ->addGroupClass('collapse')
            ->addRule('range', array('min' => -10994, 'max' => 8848, 'message' => 'Please choose value between -10994 and 8848.' ));
        $deviceForm->addElement('lat', 'hidden');
        $deviceForm->addElement('lng', 'hidden');
        $deviceForm->setDefaultValues($device);
        return $deviceForm;
    }
}

?>