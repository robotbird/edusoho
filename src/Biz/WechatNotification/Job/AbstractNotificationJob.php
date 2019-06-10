<?php

namespace Biz\WeChatNotification\Job;

use Codeages\Biz\Framework\Scheduler\AbstractJob;
use Biz\System\Service\LogService;
use Biz\WeChat\Service\WeChatService;
use Biz\Course\Service\CourseService;
use Biz\AppLoggerConstant;

class AbstractNotificationJob extends AbstractJob
{
    const OFFICIAL_TYPE = 'official';

    const LIMIT_NUM = 1000;

    public function execute()
    {
    }

    protected function sendNotifications($key, $logName, $userIds, $templateData)
    {
        $subscribedUsers = $this->getWeChatService()->findSubscribedUsersByUserIdsAndType($userIds, self::OFFICIAL_TYPE);
        $batchs = array_chunk($subscribedUsers, self::LIMIT_NUM);
        foreach ($batchs as $batch) {
            $list = array();
            foreach ($batch as $user) {
                $list[] = array_merge(array(
                    'channel' => 'wechat',
                    'to_id' => $user['openId'],
                ), $templateData);
            }
            $this->sendWeChatNotification($key, $logName, $list);
        }
    }

    protected function sendWeChatNotification($key, $logName, $list)
    {
        try {
            $result = $this->getCloudNotificationClient()->sendNotifications($list);
        } catch (\Exception $e) {
            $this->getLogService()->error(AppLoggerConstant::NOTIFY, $logName, "发送微信通知失败:template:{$key}", array('error' => $e->getMessage()));

            return;
        }

        if (empty($result['batch_sn'])) {
            $this->getLogService()->error(AppLoggerConstant::NOTIFY, $logName, "发送微信通知失败:template:{$key}", array('error' => $e->getMessage()));

            return;
        }

        $this->getNotificationService()->createWeChatNotificationRecord($result['batch_sn'], $key, $list[0]['template_args']);
    }

    protected function getCloudNotificationClient()
    {
        $biz = $this->biz;

        return $biz['qiQiuYunSdk.notification'];
    }

    /**
     * @return LogService
     */
    protected function getLogService()
    {
        return $this->biz->service('System:LogService');
    }

    /**
     * @return WeChatService
     */
    protected function getWeChatService()
    {
        return $this->biz->service('WeChat:WeChatService');
    }

    /**
     * @return CourseService
     */
    protected function getCourseService()
    {
        return $this->biz->service('Course:CourseService');
    }

    protected function getCourseSetService()
    {
        return $this->biz->service('Course:CourseSetService');
    }

    protected function getCourseMemberService()
    {
        return $this->biz->service('Course:MemberService');
    }

    protected function getTaskService()
    {
        return $this->biz->service('Task:TaskService');
    }

    protected function getNotificationService()
    {
        return $this->biz->service('Notification:NotificationService');
    }
}