<?php

/**
 * @link https://www.humhub.org/
 * @copyright Copyright (c) 2018 HumHub GmbH & Co. KG
 * @license https://www.humhub.com/licences
 *
 */
/**
 * Created by PhpStorm.
 * User: davidborn
 */

namespace humhub\modules\tasks\models\forms;

use Yii;
use yii\base\Model;
use DateInterval;
use DateTime;
use DateTimeZone;
use humhub\libs\DbDateValidator;
use humhub\modules\tasks\models\scheduling\TaskReminder;
use humhub\modules\content\components\ContentContainerActiveRecord;
use humhub\modules\content\models\Content;
use humhub\modules\tasks\models\Task;
use humhub\modules\tasks\CalendarUtils;

class TaskForm extends Model
{

    /**
     * @var integer Content visibility
     */
    public $is_public;

    /**
     * @var Task
     */
    public $task;

    /**
     * @var string start date submitted by user will be converted to db date format and timezone after validation
     */
    public $start_date;

    /**
     * @var string start time string
     */
    public $start_time;

    /**
     * @var string end date submitted by user will be converted to db date format and timezone after validation
     */
    public $end_date;

    /**
     * @var string end time string
     */
    public $end_time;

    /**
     * @var string time zone of the task
     */
    public $timeZone;

    /**
     * @var boolean defines if the request came from a calendar
     */
    public $cal;

    /**
     * @var boolean defines if the request should be redirected after success
     */
    public $redirect;

    /**
     * @var integer
     */
    public $taskListId;

    /**
     * @var array
     */
    public $newItems;

    /**
     * @var array
     */
    public $editItems;

    /**
     * @var
     */
    public $reloadListId;

    /**
     * @inheritdoc
     */
    public function init()
    {
        $this->timeZone = empty($this->timeZone) ? Yii::$app->formatter->timeZone : $this->timeZone;

        if ($this->task) {
            $this->task->scenario = Task::SCENARIO_EDIT;
            if($this->task->all_day) {
                $this->timeZone = $this->task->time_zone;
            }

            $this->translateDateTimes($this->task->start_datetime, $this->task->end_datetime, Yii::$app->timeZone, $this->timeZone);
            $this->is_public = $this->task->content->visibility;
        }
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['start_time', 'end_time'], 'date', 'type' => 'time', 'format' => $this->getTimeFormat()],
            [['start_date'], DbDateValidator::className(), 'format' => Yii::$app->params['formatter']['defaultDateFormat'], 'timeAttribute' => 'start_time', 'timeZone' => $this->timeZone],
            [['end_date'], DbDateValidator::className(), 'format' => Yii::$app->params['formatter']['defaultDateFormat'], 'timeAttribute' => 'end_time', 'timeZone' => $this->timeZone],
            [['end_date'], 'validateEndTime'],

            [['start_date', 'end_date'], 'required', 'when' => function($model) {
                return $model->task->scheduling == 1;
            }, 'whenClient' => "function (attribute, value) {
                return $('#task-scheduling').val() == 1;
            }"],
            [['start_time', 'end_time'], 'required', 'when' => function($model) {
                return $model->task->all_day == 0;
            }, 'whenClient' => "function (attribute, value) {
                return $('#task-all_day').val() == 0;
            }"],

            [['is_public'], 'integer'],
            [['newItems', 'editItems'], 'safe'],
        ];
    }

    public function getTimeFormat()
    {
        return Yii::$app->formatter->isShowMeridiem() ? 'h:mm a' : 'php:H:i';
    }

    public function beforeValidate()
    {
        $this->checkAllDay();
        return parent::beforeValidate(); // TODO: Change the autogenerated stub
    }

    public function checkAllDay()
    {
        Yii::$app->formatter->timeZone = $this->timeZone;
        
        if($this->task->all_day) {
            $date = new DateTime('now', new DateTimeZone($this->timeZone));
            $date->setTime(0,0);
            $this->start_time = Yii::$app->formatter->asTime($date, $this->getTimeFormat());
            $date->setTime(23,59);
            $this->end_time = Yii::$app->formatter->asTime($date, $this->getTimeFormat());
        }
        Yii::$app->i18n->autosetLocale();
    }

    /**
     * Validator for the endtime field.
     * Execute this after DbDateValidator
     *
     * @param string $attribute attribute name
     * @param [] $params parameters
     */
    public function validateEndTime($attribute, $params)
    {
        if (new DateTime($this->start_date) >= new DateTime($this->end_date)) {
            $this->addError($attribute, Yii::t('TasksModule.models_forms_TaskForm', 'End time must be after start time!'));
        }
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return array_merge(parent::attributeLabels(), [
            'start_date' => Yii::t('TasksModule.models_forms_TaskForm', 'Start Date'),
//            'type_id' => Yii::t('TasksModule.models_forms_TaskForm', 'Event Type'),
            'end_date' => Yii::t('TasksModule.models_forms_TaskForm', 'End Date'),
            'start_time' => Yii::t('TasksModule.models_forms_TaskForm', 'Start Time'),
            'end_time' => Yii::t('TasksModule.models_forms_TaskForm', 'End Time'),
            'timeZone' => Yii::t('TasksModule.models_forms_TaskForm', 'Time Zone'),
            'is_public' => Yii::t('TasksModule.models_forms_TaskForm', 'Public'),
        ]);
    }

    public function getTitle()
    {
        if($this->task->isNewRecord) {
           return Yii::t('TasksModule.views_index_edit', '<strong>Create</strong> new task');
        }

        return Yii::t('TasksModule.views_index_edit', '<strong>Edit</strong> task');
    }

    /**
     * Instantiates a new task for the given ContentContainerActiveRecord.
     *
     * @param ContentContainerActiveRecord $contentContainer
     */
    public function createNew(ContentContainerActiveRecord $contentContainer)
    {
        $this->task = new Task($contentContainer, Content::VISIBILITY_PRIVATE, ['task_list_id' => $this->taskListId]);
        $this->task->scenario = Task::SCENARIO_EDIT;
        $this->is_public = ($this->task->content->visibility != null) ? $this->task->content->visibility : Content::VISIBILITY_PRIVATE;
    }

    /**
     * Loads this model and the task model with the given data.
     *
     * @inheritdoc
     *
     * @param array $data
     * @param null $formName
     * @return bool
     */
    public function load($data, $formName = null)
    {
        // Make sure we load the timezone beforehand so its available in validators etc..
        if($data && isset($data[$this->formName()]) && isset($data[$this->formName()]['timeZone']) && !empty($data[$this->formName()]['timeZone'])) {
            $this->timeZone = $data[$this->formName()]['timeZone'];
        }
        if(parent::load($data) && !empty($this->timeZone)) {
            $this->task->time_zone = $this->timeZone;
        }

        $this->task->content->visibility = $this->is_public;

        if(!$this->task->load($data)) {
            return false;
        }

        return true;
    }

    /**
     * Validates and saves the task instance.
     * @return bool
     */
    public function save()
    {
        $this->task->setEditItems($this->editItems);
        $this->task->setNewItems($this->newItems);

        if(!$this->validate()) {
            return false;
        }

        // After validation the date was translated to system time zone, which we expect in the database.
        $this->task->start_datetime = $this->start_date;
        $this->task->end_datetime = $this->end_date;

        // The form expects user time zone, so we translate back from app to user timezone
        $this->translateDateTimes($this->task->start_datetime, $this->task->end_datetime, Yii::$app->timeZone, $this->timeZone);

        // We save the list ids to reload in the view this has to be called before $task->save()!
        $this->reloadListId = $this->getListIdsToReload();

        if($this->task->save()) {
            return true;
        }

        return false;
    }

    public function showTimeFields()
    {
        return !$this->task->all_day;
    }

    private function getListIdsToReload()
    {
        $result = [$this->task->task_list_id];
        if(!$this->task->isNewRecord && $this->task->isAttributeChanged('task_list_id')) {
            $result[] = $this->task->getOldAttribute('task_list_id');
        }
        return $result;
    }

    /**
     * Translates the given start and end dates from $sourceTimeZone to $targetTimeZone and populates the form start/end time
     * and dates.
     *
     * By default $sourceTimeZone is the forms timeZone e.g user timeZone and $targetTimeZone is the app timeZone.
     *
     * @param string $start start string date in $sourceTimeZone
     * @param string $end end string date in $targetTimeZone
     * @param string $sourceTimeZone
     * @param string $targetTimeZone
     */
    public function translateDateTimes($start = null, $end = null, $sourceTimeZone = null, $targetTimeZone = null, $dateFormat = 'php:Y-m-d H:i:s e')
    {
        if(!$start) {
            return;
        }

        $sourceTimeZone = (empty($sourceTimeZone)) ? $this->timeZone : $sourceTimeZone;
        $targetTimeZone = (empty($targetTimeZone)) ? Yii::$app->timeZone : $targetTimeZone;

        $startTime = new DateTime($start, new DateTimeZone($sourceTimeZone));
        $endTime = new DateTime($end, new DateTimeZone($sourceTimeZone));

        Yii::$app->formatter->timeZone = $targetTimeZone;

        // Todo: check if this is really necessary
        // Fix FullCalendar EndTime
        if (CalendarUtils::isFullDaySpan($startTime, $endTime, true)) {
            // In Fullcalendar the EndTime is the moment AFTER the event so we substract one second
            $endTime->sub(new DateInterval("PT1S"));
            $this->task->all_day = 1;
        }

        $this->start_date = Yii::$app->formatter->asDateTime($startTime, $dateFormat);
        $this->start_time = Yii::$app->formatter->asTime($startTime, $this->getTimeFormat());

        $this->end_date = Yii::$app->formatter->asDateTime($endTime, $dateFormat);
        $this->end_time = Yii::$app->formatter->asTime($endTime, $this->getTimeFormat());

        Yii::$app->i18n->autosetLocale();
    }

    public function getSubmitUrl()
    {
        return $this->task->content->container->createUrl('edit', [
            'id' => $this->task->id,
            'cal' => $this->cal,
            'redirect' => $this->redirect,
            'listId' => $this->taskListId
        ]);
    }

    public function getDeleteUrl()
    {
        return $this->task->content->container->createUrl('delete', [
            'id' => $this->task->id,
            'cal' => $this->cal,
            'redirect' => $this->redirect
        ]);
    }

    public function getTaskAssignedPickerUrl()
    {
        return $this->task->content->container->createUrl('/tasks/task/task-assigned-picker', ['id' => $this->task->id]);
    }

    public function getTaskResponsiblePickerUrl()
    {
        return $this->task->content->container->createUrl('/tasks/task/task-assigned-picker', ['id' => $this->task->id]);
    }

    public function updateTime($start = null, $end = null)
    {
        $this->task->time_zone = Yii::$app->formatter->timeZone;
        $this->translateDateTimes($start, $end, null, null, 'php:Y-m-d H:i:s');

        $this->task->start_datetime = $this->start_date;
        $this->task->end_datetime = $this->end_date;

        return $this->task->updateAttributes(['start_datetime' => $this->task->start_datetime, 'end_datetime' => $this->task->end_datetime]);
    }

    public function getContentContainer()
    {
        return $this->task->content->container;
    }

    public function getRemindModeItems()
    {
        return [
            TaskReminder::REMIND_ONE_HOUR => Yii::t('TasksModule.models_taskReminder', 'At least 1 Hour before'),
            TaskReminder::REMIND_TWO_HOURS => Yii::t('TasksModule.models_taskReminder', 'At least 2 Hours before'),
            TaskReminder::REMIND_ONE_DAY => Yii::t('TasksModule.models_taskReminder', '1 Day before'),
            TaskReminder::REMIND_TWO_DAYS => Yii::t('TasksModule.models_taskReminder', '2 Days before'),
            TaskReminder::REMIND_ONE_WEEK => Yii::t('TasksModule.models_taskReminder', '1 Week before'),
            TaskReminder::REMIND_TWO_WEEKS => Yii::t('TasksModule.models_taskReminder', '2 Weeks before'),
            TaskReminder::REMIND_THREE_WEEKS => Yii::t('TasksModule.models_taskReminder', '3 Weeks before'),
            TaskReminder::REMIND_ONE_MONTH => Yii::t('TasksModule.models_taskReminder', '1 Month before'),
        ];
    }
}