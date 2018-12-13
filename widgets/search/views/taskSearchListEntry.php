<?php
/**
 * @link https://www.humhub.org/
 * @copyright Copyright (c) 2018 HumHub GmbH & Co. KG
 * @license https://www.humhub.com/licences
 *
 */

use humhub\libs\Html;
use humhub\modules\tasks\helpers\TaskUrl;
use humhub\modules\tasks\models\Task;
use humhub\modules\tasks\widgets\TaskBadge;
use humhub\modules\tasks\widgets\TaskPercentageBar;
use humhub\modules\tasks\widgets\TaskUserList;
use humhub\modules\space\widgets\Image;
use humhub\widgets\Button;

/* @var $task \humhub\modules\tasks\models\Task */
/* @var $canEdit boolean */
/* @var $filterResult boolean */

$color = $task->getColor() ? $task->getColor() : $this->theme->variable('info');


?>


<div class="media task" data-task-url="<?= TaskUrl::viewTask($task) ?>">
    <div class="task-head">
        <div class="media-body clearfix">
            <div class="task-controls" style="display:inline">
                <?= Image::widget([
                    'space' => $task->content->container,
                    'width' => '24',
                    'showTooltip' => true,
                    'link' => true
                ]) ?>
            </div>

            <div style="margin-right:2px;display:inline-block">
                <h4 class="media-heading" style="display:inline-block">
                    <?= Html::encode($task->title); ?>
                </h4>
            </div>

            <?= TaskBadge::widget(['task' => $task]) ?>

            <div class="assigned-users pull-right" style="display: inline;border:0">
                <?= TaskUserList::widget(['users' => $task->taskResponsibleUsers, 'style' => 'border:2px solid ' . $this->theme->variable('info'), 'type' => Task::USER_RESPONSIBLE]) ?>
                <?= TaskUserList::widget(['users' => $task->taskAssignedUsers]) ?>
            </div>

            <?php if ($task->isInProgress()) : ?>
                <div class="task-controls  pull-right hidden-xs" style="width:50px;height:24px;display:inline-block;padding-top:5px;">
                    <?= TaskPercentageBar::widget(['task' => $task, 'filterResult' => $filterResult]) ?>
                </div>
            <?php endif; ?>

        </div>
    </div>
</div>