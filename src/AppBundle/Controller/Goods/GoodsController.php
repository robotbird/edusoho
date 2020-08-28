<?php

namespace AppBundle\Controller\Goods;

use ApiBundle\Api\ApiRequest;
use AppBundle\Controller\BaseController;
use Biz\Common\CommonException;
use Biz\Goods\Service\GoodsService;
use Biz\User\Service\UserFieldService;
use Symfony\Component\HttpFoundation\Request;

class GoodsController extends BaseController
{
    public function showAction(Request $request, $id)
    {
        $goodsApiRequest = new ApiRequest("/api/goods/{$id}", 'GET');
        $goods = $this->container->get('api_resource_kernel')->handleApiRequest($goodsApiRequest);
        $goodsComponentsApiRequest = new ApiRequest("/api/goods/{$id}/components", 'GET');
        $goodsComponents = $this->container->get('api_resource_kernel')->handleApiRequest($goodsComponentsApiRequest);
        $this->getGoodsService()->hitGoods($id);

        return $this->render(
            'goods/show.html.twig',
            [
                'goods' => $goods,
                'goodsComponents' => $goodsComponents,
            ]
        );
    }

    public function minScreenShowAction(Request $request, $id)
    {
        return $this->render('goods/min-screen-show.html.twig', []);
    }

    public function buyFLowModalAction(Request $request)
    {
        if (!in_array($request->query->get('template'), ['no-remain', 'payments-disabled', 'avatar-alert', 'fill-user-info'])) {
            $this->createNewException(CommonException::ERROR_PARAMETER());
        }

        $params = [];

        if ('fill-user-info' === $request->query->get('template')) {
            $userFields = $this->getUserFieldService()->getEnabledFieldsOrderBySeq();
            $user = $this->getCurrentUser();
            $userInfo = $this->getUserService()->getUserProfile($user['id']);
            $userInfo['approvalStatus'] = $user['approvalStatus'];

            $params['userFields'] = $userFields;
            $params['user'] = $userInfo;
        }

        return $this->render(
            'buy-flow/'.$request->query->get('template').'-modal.html.twig', $params);
    }

    /**
     * @return UserFieldService
     */
    protected function getUserFieldService()
    {
        return $this->createService('User:UserFieldService');
    }

    /**
     * @return GoodsService
     */
    protected function getGoodsService()
    {
        return $this->createService('Goods:GoodsService');
    }
}
