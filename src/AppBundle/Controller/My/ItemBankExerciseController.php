<?php

namespace AppBundle\Controller\My;

use AppBundle\Common\ArrayToolkit;
use AppBundle\Common\Paginator;
use AppBundle\Controller\BaseController;
use Biz\ItemBankExercise\Service\ExerciseService;
use Biz\QuestionBank\Service\QuestionBankService;
use Symfony\Component\HttpFoundation\Request;

class ItemBankExerciseController extends BaseController
{
    public function teachingAction(Request $request, $filter)
    {
        $user = $this->getCurrentUser();

        if (!$user->isTeacher()) {
            return $this->createMessageResponse('error', 'my.teaching.view.forbidden');
        }

        $conditions = [
            'teacherIds' => $user['id'],
        ];

        $paginator = new Paginator(
            $request,
            $this->getItemBankExerciseService()->count($conditions),
            10
        );

        $itemBankExercises = $this->getItemBankExerciseService()->search(
            $conditions,
            ['createdTime' => 'DESC'],
            $paginator->getOffsetCount(),
            $paginator->getPerPageCount()
        );

        $questionBanks = ArrayToolkit::column($itemBankExercises, 'questionBankId');
        $questionBanks = $this->getQuestionBankService()->findQuestionBanksByIds($questionBanks);
        $questionBanks = ArrayToolkit::index($questionBanks, 'id');
        foreach ($itemBankExercises as &$itemBankExercise) {
            $itemBankExercise['assessmentNum'] = $questionBanks[$itemBankExercise['questionBankId']]['itemBank']['assessment_num'];
            $itemBankExercise['itemNum'] = $questionBanks[$itemBankExercise['questionBankId']]['itemBank']['item_num'];
        }

        return $this->render('my/teaching/item-bank-exercise.html.twig', [
            'itemBankExercises' => $itemBankExercises,
            'paginator' => $paginator,
            'filter' => $filter,
        ]);
    }

    /**
     * @return ExerciseService
     */
    protected function getItemBankExerciseService()
    {
        return $this->getBiz()->service('ItemBankExercise:ExerciseService');
    }

    /**
     * @return QuestionBankService
     */
    protected function getQuestionBankService()
    {
        return $this->createService('QuestionBank:QuestionBankService');
    }
}
