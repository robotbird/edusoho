<?php

namespace Biz\InformationCollect\Service\Impl;

use AppBundle\Common\ArrayToolkit;
use Biz\BaseService;
use Biz\Common\CommonException;
use Biz\InformationCollect\Dao\EventDao;
use Biz\InformationCollect\Dao\ItemDao;
use Biz\InformationCollect\Dao\LocationDao;
use Biz\InformationCollect\InformationCollectException;
use Biz\InformationCollect\Service\EventService;

class EventServiceImpl extends BaseService implements EventService
{
    public function createEventWithLocations(array $fields)
    {
        if (!ArrayToolkit::requireds($fields, ['title', 'action', 'formTitle', 'status', 'allowSkip'])) {
            $this->createServiceException(CommonException::ERROR_PARAMETER_MISSING());
        }

        $event = ArrayToolkit::filter($fields, [
            'title' => '',
            'action' => '',
            'formTitle' => '',
            'status' => 'open',
            'allowSkip' => 1,
        ]);

        $event['creator'] = $this->getCurrentUser()->getId();

        $event = $this->getEventDao()->create($event);

        $this->editEventLocations($event, $fields);

        return $event;
    }

    public function updateEventWithLocations($id, $updateFields)
    {
        $event = $this->getEventDao()->get($id);
        if (empty($event)) {
            $this->createServiceException(InformationCollectException::NOTFOUND_COLLECTION());
        }

        $updateEventFields = ArrayToolkit::filter($updateFields, [
            'title' => '',
            'action' => '',
            'formTitle' => '',
            'status' => 'open',
            'allowSkip' => 1,
        ]);

        $event = $this->getEventDao()->update($id, $updateEventFields);
        $this->editEventLocations($event, $updateFields);
        return $event;
    }

    public function count($conditions)
    {
        return $this->getEventDao()->count($conditions);
    }

    public function search($conditions, $orderBy, $start, $limit)
    {
        $conditions = $this->_prepareConditions($conditions);

        return $this->getEventDao()->search($conditions, $orderBy, $start, $limit);
    }

    private function _prepareConditions($conditions)
    {
        $conditions = array_filter($conditions, function ($value) {
            if (0 == $value) {
                return true;
            }

            return !empty($value);
        });

        if (!empty($conditions['startDate'])) {
            $conditions['startDate'] = strtotime($conditions['startDate']);
        }

        if (!empty($conditions['endDate'])) {
            $conditions['endDate'] = strtotime($conditions['endDate']);
        }

        return $conditions;
    }

    public function getEventByActionAndLocation($action, array $location)
    {
        if (!ArrayToolkit::requireds($location, ['targetType', 'targetId'], true)) {
            return null;
        }

        return $this->getEventDao()->getByActionAndLocation($action, $location);
    }

    public function get($id)
    {
        return $this->getEventDao()->get($id);
    }

    public function findItemsByEventId($eventId)
    {
        return $this->getItemDao()->findByEventId($eventId);
    }

    public function closeCollection($id)
    {
        $collection = $this->get($id);
        if (empty($collection)) {
            $this->createNewException(InformationCollectException::NOTFOUND_COLLECTION());
        }

        return $this->getEventDao()->update($id, ['status' => 'close']);
    }

    public function openCollection($id)
    {
        $collection = $this->get($id);
        if (empty($collection)) {
            $this->createNewException(InformationCollectException::NOTFOUND_COLLECTION());
        }

        return $this->getEventDao()->update($id, ['status' => 'open']);
    }

    public function getEventLocations($id)
    {
        $collection = $this->get($id);
        if (empty($collection)) {
            $this->createNewException(InformationCollectException::NOTFOUND_COLLECTION());
        }

        $locations = $this->getLocationDao()->search(['eventId' => $id], [], 0, PHP_INT_MAX);

        $locationInfo = [];
        foreach ($locations as $location) {
            if ('course' == $location['targetType']) {
                $locationInfo['course'][] = $location['targetId'];
            } else {
                $locationInfo['classroom'][] = $location['targetId'];
            }
        }

        return $locationInfo;
    }

    public function editEventLocations($event, $locationFields)
    {
        if (empty($locationFields['targetTypes'])) {
            return;
        }

        if (!ArrayToolkit::requireds($locationFields, ['locationType'])) {
            $this->createServiceException(CommonException::ERROR_PARAMETER_MISSING());
        }

        foreach ($locationFields['targetTypes'] as $type) {
            if (!in_array($type, [self::TARGET_TYPE_CLASSROOM, self::TARGET_TYPE_COURSE])) {
                continue;
            }
            if ($type === self::TARGET_TYPE_COURSE) {
                $targetIds = empty($locationFields['courseIds']) ? [] : json_decode($locationFields['courseIds'], true);
            } else {
                $targetIds = empty($locationFields['classroomIds']) ? [] : json_decode($locationFields['classroomIds'], true);
            }

            if (empty($targetIds)) {
                continue;
            }

            // if ($locationFields['locationType'] == 'all') {

            // } elseif($locationFields['locationType'] == 'part') {
                $this->batchDeleteLocations($event['id'], $type, $targetIds);
                $this->batchCreateLocations($event['id'], $event['action'], $type, $targetIds);
            // }
        }
    }

    private function batchDeleteLocations($eventId, $targetType, $targetIds)
    {
        $existedLocations = $this->getLocationDao()->search(['targetType' => $targetType, 'targetIds' => $targetIds, ['excludeEventId' => $eventId]], [], 0, count($targetIds), ['targetId']);
        $existedLocationsTargetIds = array_values(array_column($existedLocations, 'targetId'));
        $deleteLocationsTargetIds = array_intersect($targetIds, $existedLocationsTargetIds);
        if (!empty($deleteLocationsTargetIds)) {
            $this->getLocationDao()->batchDelete(['targetType' => $targetType, 'targetIds' => $deleteLocationsTargetIds]);
        }
    }

    private function batchCreateLocations($eventId, $action, $targetType, array $targetIds)
    {
        if (empty($targetIds)) {
            return;
        }
        $existedLocations = $this->getLocationDao()->search(['targetType' => $targetType, 'targetIds' => $targetIds, ['eventId' => $eventId]], [], 0, count($targetIds), ['targetId']);
        $existedLocationsTargetIds = array_values(array_column($existedLocations, 'targetId'));
        $newLocationsTargetIds = array_diff($targetIds, $existedLocationsTargetIds);

        $locations = [];
        foreach ($newLocationsTargetIds as $targetId) {
            $locations[] = [
                'eventId' => $eventId,
                'targetType' => $targetType,
                'action' => $action,
                'targetId' => $targetId
            ];
        }

        if (empty($locations)) {
            return;
        }

        $this->getLocationDao()->batchCreate($locations);
    }

    public function searchLocations(array $conditions, array $orderBys, $start = 0, $limit = 20, $columns = [])
    {
        return $this->getLocationDao()->search($conditions, $orderBys, $start, $limit, $columns);
    }

    public function countLocations(array $conditions)
    {
        return $this->getLocationDao()->count($conditions);
    }

    /**
     * @return EventDao
     */
    protected function getEventDao()
    {
        return $this->createDao('InformationCollect:EventDao');
    }

    /**
     * @return LocationDao
     */
    protected function getLocationDao()
    {
        return $this->createDao('InformationCollect:LocationDao');
    }

    /**
     * @return ItemDao
     */
    protected function getItemDao()
    {
        return $this->createDao('InformationCollect:ItemDao');
    }
}
