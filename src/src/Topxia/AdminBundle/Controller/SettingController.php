<?php

namespace Topxia\AdminBundle\Controller;

use Topxia\Common\FileToolkit;
use Topxia\Common\JsonToolkit;
use Topxia\Service\Common\Mail\MailFactory;
use Topxia\Service\Util\EdusohoLiveClient;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Topxia\Service\CloudPlatform\CloudAPIFactory;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;

class SettingController extends BaseController
{
    public function postNumRulesAction(Request $request)
    {
        if ($request->getMethod() == 'POST') {
            $setting = $request->request->get('setting', array());
            $this->getSettingService()->set('post_num_rules', $setting);
            $this->getLogService()->info('system', 'update_settings', '更新PostNumSetting设置', $setting);
            $this->setFlashMessage('success', $this->trans('设置已保存！'));
        }

        $setting = $this->getSettingService()->get('post_num_rules', array());
        $setting = JsonToolkit::prettyPrint(json_encode($setting));

        return $this->render('TopxiaAdminBundle:System:post-num-rules.html.twig', array(
            'setting' => $setting
        ));
    }

    public function mobileAction(Request $request)
    {
        $operationMobile = $this->getSettingService()->get('operation_mobile', array());
        $courseGrids     = $this->getSettingService()->get('operation_course_grids', array());
        $settingMobile   = $this->getSettingService()->get('mobile', array());

        $default = array(
            'enabled'  => 1, // 网校状态
            'ver'      => 1, //是否是新版
            'about'    => '', // 网校简介
            'logo'     => '', // 网校Logo
            'appname'  => '',
            'appabout' => '',
            'applogo'  => '',
            'appcover' => '',
            'notice'   => '', //公告
            'splash1'  => '', // 启动图1
            'splash2'  => '', // 启动图2
            'splash3'  => '', // 启动图3
            'splash4'  => '', // 启动图4
            'splash5'  => '' // 启动图5
        );

        $mobile = array_merge($default, $settingMobile);

        if ($request->getMethod() == 'POST') {
            $settingMobile = $request->request->all();

            $mobile = array_merge($settingMobile, $operationMobile, $courseGrids);

            $this->getSettingService()->set('operation_mobile', $operationMobile);
            $this->getSettingService()->set('operation_course_grids', $courseGrids);
            $this->getSettingService()->set('mobile', $mobile);

            $this->getLogService()->info('system', 'update_settings', '更新移动客户端设置', $mobile);
            $this->setFlashMessage('success', $this->trans('移动客户端设置已保存！'));
        }

        $result = CloudAPIFactory::create('leaf')->get('/me');

        $mobileCode = ((array_key_exists("mobileCode", $result) && !empty($result["mobileCode"])) ? $result["mobileCode"] : "edusohov3");

        //是否拥有定制app
        $hasMobile = isset($result['hasMobile']) ? $result['hasMobile'] : 0;
        return $this->render('TopxiaAdminBundle:System:mobile.setting.html.twig', array(
            'mobile'     => $mobile,
            'mobileCode' => $mobileCode,
            'hasMobile'  => $hasMobile
        ));
    }

    public function mobileIapProductAction(Request $request)
    {
        $products = $this->getSettingService()->get('mobile_iap_product', array());
        if ($request->getMethod() == 'POST') {
            $fileds = $request->request->all();

            //新增校验
            if (empty($fileds['productId']) || empty($fileds['title']) || empty($fileds['price']) || !is_numeric($fileds['price'])) {
                $this->setFlashMessage('danger', $this->trans('产品ID或商品名称或价格输入不正确'));
                return $this->redirect($this->generateUrl('admin_setting_mobile_iap_product'));
            }

            //新增
            $products[$fileds['productId']] = array(
                'productId' => $fileds['productId'],
                'title' => $fileds['title'],
                'price' => $fileds['price']
            );
            $this->getSettingService()->set('mobile_iap_product', $products);

            $this->getLogService()->info('system', 'update_settings', '更新IOS内购产品设置', $products);
            $this->setFlashMessage('success', $this->trans('IOS内购产品设置已保存'));
            return $this->redirect($this->generateUrl('admin_setting_mobile_iap_product'));
            
        }

        return $this->render('TopxiaAdminBundle:System:mobile-iap-product.html.twig', array(
            'products'     => $products
        ));
    }

    public function mobileIapProductDeleteAction(Request $request, $productId)
    {
        $products = $this->getSettingService()->get('mobile_iap_product', array());

        if (array_key_exists($productId, $products)) {
            unset($products[$productId]);
        }

        $this->getSettingService()->set('mobile_iap_product', $products);

        return $this->createJsonResponse(true);
    }

    public function mobilePictureUploadAction(Request $request, $type)
    {
        $fileId = $request->request->get('id');
        $file   = $this->getFileService()->getFileObject($fileId);

        if (!FileToolkit::isImageFile($file)) {
            throw $this->createAccessDeniedException($this->trans('图片格式不正确！'));
        }

        $filename  = 'mobile_picture'.time().'.'.$file->getExtension();
        $directory = "{$this->container->getParameter('topxia.upload.public_directory')}/system";
        $file      = $file->move($directory, $filename);

        $mobile        = $this->getSettingService()->get('mobile', array());
        $mobile[$type] = "{$this->container->getParameter('topxia.upload.public_url_path')}/system/{$filename}";
        $mobile[$type] = ltrim($mobile[$type], '/');

        $this->getSettingService()->set('mobile', $mobile);

        $this->getLogService()->info('system', 'update_settings', '更新网校$type图片', array($type => $mobile[$type]));

        $response = array(
            'path' => $mobile[$type],
            'url'  => $this->container->get('templating.helper.assets')->getUrl($mobile[$type])
        );

        return new Response(json_encode($response));
    }

    public function mobilePictureRemoveAction(Request $request, $type)
    {
        $setting        = $this->getSettingService()->get("mobile");
        $setting[$type] = '';

        $this->getSettingService()->set('mobile', $setting);

        $this->getLogService()->info('system', 'update_settings', "移除网校{$type}图片");

        return $this->createJsonResponse(true);
    }

    public function logoUploadAction(Request $request)
    {
        $fileId     = $request->request->get('id');
        $objectFile = $this->getFileService()->getFileObject($fileId);

        if (!FileToolkit::isImageFile($objectFile)) {
            throw $this->createAccessDeniedException($this->trans('图片格式不正确！'));
        }

        $file   = $this->getFileService()->getFile($fileId);
        $parsed = $this->getFileService()->parseFileUri($file["uri"]);

        $site = $this->getSettingService()->get('site', array());

        $oldFileId            = empty($site['logo_file_id']) ? null : $site['logo_file_id'];
        $site['logo_file_id'] = $fileId;
        $site['logo']         = "{$this->container->getParameter('topxia.upload.public_url_path')}/".$parsed["path"];
        $site['logo']         = ltrim($site['logo'], '/');

        $this->getSettingService()->set('site', $site);

        if ($oldFileId) {
            $this->getFileService()->deleteFile($oldFileId);
        }

        $this->getLogService()->info('system', 'update_settings', '更新站点LOGO', array('logo' => $site['logo']));

        $response = array(
            'path' => $site['logo'],
            'url'  => $this->container->get('templating.helper.assets')->getUrl($site['logo'])
        );

        return $this->createJsonResponse($response);
    }

    public function logoRemoveAction(Request $request)
    {
        $setting         = $this->getSettingService()->get("site");
        $setting['logo'] = '';

        $fileId                  = empty($setting['logo_file_id']) ? null : $setting['logo_file_id'];
        $setting['logo_file_id'] = '';

        $this->getSettingService()->set('site', $setting);

        if ($fileId) {
            $this->getFileService()->deleteFile($fileId);
        }

        $this->getLogService()->info('system', 'update_settings', '移除站点LOGO');

        return $this->createJsonResponse(true);
    }

    public function liveLogoUploadAction(Request $request)
    {
        $fileId     = $request->request->get('id');
        $objectFile = $this->getFileService()->getFileObject($fileId);

        if (!FileToolkit::isImageFile($objectFile)) {
            throw $this->createAccessDeniedException($this->trans('图片格式不正确！'));
        }

        $file   = $this->getFileService()->getFile($fileId);
        $parsed = $this->getFileService()->parseFileUri($file["uri"]);

        $site = $this->getSettingService()->get('course', array());

        $oldFileId                 = empty($site['live_logo_file_id']) ? null : $site['live_logo_file_id'];
        $site['live_logo_file_id'] = $fileId;
        $site['live_logo']         = "{$this->container->getParameter('topxia.upload.public_url_path')}/".$parsed["path"];
        $site['live_logo']         = ltrim($site['live_logo'], '/');

        $this->getSettingService()->set('course', $site);

        if ($oldFileId) {
            $this->getFileService()->deleteFile($oldFileId);
        }

        $this->getLogService()->info('system', 'update_settings', '更新直播LOGO', array('live_logo' => $site['live_logo']));

        $response = array(
            'path' => $site['live_logo'],
            'url'  => $this->container->get('templating.helper.assets')->getUrl($site['live_logo'])
        );

        return $this->createJsonResponse($response);
    }

    public function liveLogoRemoveAction(Request $request)
    {
        $setting              = $this->getSettingService()->get("course");
        $setting['live_logo'] = '';

        $fileId                       = empty($setting['live_logo_file_id']) ? null : $setting['live_logo_file_id'];
        $setting['live_logo_file_id'] = '';

        $this->getSettingService()->set('course', $setting);

        if ($fileId) {
            $this->getFileService()->deleteFile($fileId);
        }

        $this->getLogService()->info('system', 'update_settings', '移除直播LOGO');

        return $this->createJsonResponse(true);
    }

    public function faviconUploadAction(Request $request)
    {
        $fileId     = $request->request->get('id');
        $objectFile = $this->getFileService()->getFileObject($fileId);

        if (!FileToolkit::isImageFile($objectFile)) {
            throw $this->createAccessDeniedException($this->trans('图片格式不正确！'));
        }

        $file   = $this->getFileService()->getFile($fileId);
        $parsed = $this->getFileService()->parseFileUri($file["uri"]);

        $site = $this->getSettingService()->get('site', array());

        $oldFileId               = empty($site['favicon_file_id']) ? null : $site['favicon_file_id'];
        $site['favicon_file_id'] = $fileId;
        $site['favicon']         = "{$this->container->getParameter('topxia.upload.public_url_path')}/".$parsed["path"];
        $site['favicon']         = ltrim($site['favicon'], '/');

        $this->getSettingService()->set('site', $site);

        if ($oldFileId) {
            $this->getFileService()->deleteFile($oldFileId);
        }

        //浏览器图标覆盖默认图标
        copy($this->getServiceKernel()->getParameter('kernel.root_dir').'/../web/'.$site['favicon'], $this->getServiceKernel()->getParameter('kernel.root_dir').'/../web/favicon.ico');

        $this->getLogService()->info('system', 'update_settings', '更新浏览器图标', array('favicon' => $site['favicon']));

        $response = array(
            'path' => $site['favicon'],
            'url'  => $this->container->get('templating.helper.assets')->getUrl($site['favicon'])
        );

        return $this->createJsonResponse($response);
    }

    public function faviconRemoveAction(Request $request)
    {
        $setting            = $this->getSettingService()->get("site");
        $setting['favicon'] = '';

        $fileId                     = empty($setting['favicon_file_id']) ? null : $setting['favicon_file_id'];
        $setting['favicon_file_id'] = '';

        $this->getSettingService()->set('site', $setting);

        if ($fileId) {
            $this->getFileService()->deleteFile($fileId);
        }

        $this->getLogService()->info('system', 'update_settings', '移除站点浏览器图标');

        return $this->createJsonResponse(true);
    }

    protected function setCloudSmsKey($key, $val)
    {
        $setting       = $this->getSettingService()->get('cloud_sms', array());
        $setting[$key] = $val;
        $this->getSettingService()->set('cloud_sms', $setting);
    }

    public function mailerAction(Request $request)
    {
        if ($this->getWebExtension()->isTrial()) {
            return $this->render('TopxiaAdminBundle:System:mailer.html.twig', array());
        }

        $mailer = $this->getSettingService()->get('mailer', array());

        $default = array(
            'enabled'  => 0,
            'host'     => '',
            'port'     => '',
            'username' => '',
            'password' => '',
            'from'     => '',
            'name'     => ''
        );
        $mailer = array_merge($default, $mailer);

        if ($request->getMethod() == 'POST') {
            $mailer = $request->request->all();
            $this->getSettingService()->set('mailer', $mailer);
            $mailerWithoutPassword             = $mailer;
            $mailerWithoutPassword['password'] = '******';
            $this->getLogService()->info('system', 'update_settings', '更新邮件服务器设置', $mailerWithoutPassword);
            $this->setFlashMessage('success', $this->trans('电子邮件设置已保存！'));
        }

        $status = $this->checkMailerStatus();
        return $this->render('TopxiaAdminBundle:System:mailer.html.twig', array(
            'mailer' => $mailer,
            'status' => $status
        ));
    }

    public function mailerTestAction(Request $request)
    {
        $user        = $this->getCurrentUser();
        $mailOptions = array(
            'to'       => $user['email'],
            'template' => 'email_system_self_test'
        );
        $mail = MailFactory::create($mailOptions);
        try {
            $mail->send();
            return $this->createJsonResponse(array(
                'status' => true
            ));
        } catch (\Exception $e) {
            return $this->createJsonResponse(array(
                'status'  => false,
                'message' => $e->getMessage()
            ));
        }
    }

    protected function checkMailerStatus()
    {
        $cloudEmail = $this->getSettingService()->get('cloud_email', array());
        $mailer     = $this->getSettingService()->get('mailer', array());
        $status     = "";

        if (!empty($cloudEmail) && $cloudEmail['status'] == 'enable') {
            return $status = "cloud_email";
        }

        if (!empty($mailer) && $mailer['enabled'] == 1) {
            return $status = "email";
        }

        return $status;
    }

    public function defaultAction(Request $request)
    {
        $defaultSetting = $this->getSettingService()->get('default', array());
        $path           = $this->container->getParameter('kernel.root_dir').'/../web/assets/img/default/';

        $default = $this->getDefaultSet();

        $defaultSetting = array_merge($default, $defaultSetting);

        if ($request->getMethod() == 'POST') {
            $defaultSetting = $request->request->all();

            if (!isset($defaultSetting['user_name'])) {
                $defaultSetting['user_name'] = $this->trans('学员');
            }

            if (!isset($defaultSetting['chapter_name'])) {
                $defaultSetting['chapter_name'] = $this->trans('章');
            }

            if (!isset($defaultSetting['part_name'])) {
                $defaultSetting['part_name'] = $this->trans('节');
            }

            $default        = $this->getSettingService()->get('default', array());
            $defaultSetting = array_merge($default, $defaultSetting);

            $this->getSettingService()->set('default', $defaultSetting);
            $this->getLogService()->info('system', 'update_settings', '更新系统默认设置', $defaultSetting);
            $this->setFlashMessage('success', $this->trans('系统默认设置已保存！'));
        }

        return $this->render('TopxiaAdminBundle:System:default.html.twig', array(
            'defaultSetting'  => $defaultSetting,
            'hasOwnCopyright' => false
        ));
    }

    protected function getDefaultSet()
    {
        $default = array(
            'defaultAvatar'                => 0,
            'defaultCoursePicture'         => 0,
            'defaultAvatarFileName'        => 'avatar',
            'defaultCoursePictureFileName' => 'coursePicture',
            'articleShareContent'          => $this->trans('我正在看{{articletitle}}，关注{{sitename}}，分享知识，成就未来。'),
            'courseShareContent'           => $this->trans('我正在学习{{course}}，收获巨大哦，一起来学习吧！'),
            'groupShareContent'            => $this->trans('我在{{groupname}}小组，看{{threadname}}，很不错哦，一起来看看吧！'),
            'classroomShareContent'        => $this->trans('我正在学习{{classroom}}，收获巨大哦，一起来学习吧！'),
            'user_name'                    => $this->trans('学员'),
            'chapter_name'                 => $this->trans('章'),
            'part_name'                    => $this->trans('节')
        );

        return $default;
    }

    public function ipBlacklistAction(Request $request)
    {
        $settingService = $this->getSettingService();

        if ($request->getMethod() == 'POST') {
            $data = $request->request->all();

            $purifiedBlackIps = trim(str_replace(array("\r\n", "\n", "\r"), " ", $data['blackListIps']));
            $purifiedWhiteIps = isset($data['whiteListIps']) ? $data['whiteListIps'] : null;
            $purifiedWhiteIps = trim(str_replace(array("\r\n", "\n", "\r"), " ", $purifiedWhiteIps));

            $logService = $this->getLogService();

            if (empty($purifiedBlackIps)) {
                $settingService->delete('blacklist_ip');

                $blackListIps['ips'] = array();
            } else {
                $blackListIps['ips'] = array_filter(explode(' ', $purifiedBlackIps));
                $settingService->set('blacklist_ip', $blackListIps);
            }

            if (empty($purifiedWhiteIps)) {
                $settingService->delete('whitelist_ip');

                $whiteListIps['ips'] = array();
            } else {
                $whiteListIps['ips'] = array_filter(explode(' ', $purifiedWhiteIps));
                $settingService->set('whitelist_ip', $whiteListIps);
            }

            $logService->info('system', 'update_settings', '更新IP黑名单/白名单', 
                array('blacklist_ip' => $blackListIps['ips'], 
                    'whitelist_ip' => $whiteListIps['ips']));

            $this->setFlashMessage('success', $this->trans('保存成功！'));
        }

        $blackListIps = $settingService->get('blacklist_ip', array());
        $whiteListIps = $settingService->get('whitelist_ip', array());

        if (!empty($blackListIps)) {
            $default['ips'] = join("\n", $blackListIps['ips']);
            $blackListIps = array_merge($blackListIps, $default);
        } else {
            $blackListIps = array();
        }

        if (!empty($whiteListIps)) {
            $default['ips'] = join("\n", $whiteListIps['ips']);
            $whiteListIps = array_merge($whiteListIps, $default);
        } else {
            $whiteListIps = array();
        }

        return $this->render('TopxiaAdminBundle:System:ip-blacklist.html.twig', array(
            'blackListIps' => $blackListIps, 'whiteListIps' => $whiteListIps
        ));
    }

    public function customerServiceAction(Request $request)
    {
        $customerServiceSetting = $this->getSettingService()->get('customerService', array());

        $default = array(
            'customer_service_mode' => 'closed',
            'customer_of_qq'        => '',
            'customer_of_mail'      => '',
            'customer_of_phone'     => ''
        );

        $customerServiceSetting = array_merge($default, $customerServiceSetting);

        if ($request->getMethod() == 'POST') {
            $customerServiceSetting = $request->request->all();
            $this->getSettingService()->set('customerService', $customerServiceSetting);
            $this->getLogService()->info('system', 'customerServiceSetting', '客服管理设置', $customerServiceSetting);
            $this->setFlashMessage('success', $this->trans('客服管理设置已保存！'));
        }

        return $this->render('TopxiaAdminBundle:System:customer-service.html.twig', array(
            'customerServiceSetting' => $customerServiceSetting
        ));
    }

    public function courseSettingAction(Request $request)
    {
        $courseSetting = $this->getSettingService()->get('course', array());

        $client   = new EdusohoLiveClient();
        $capacity = $client->getCapacity();

        $default = array(
            'welcome_message_enabled'  => '0',
            'welcome_message_body'     => $this->trans('{{nickname}},欢迎加入课程{{course}}'),
            'buy_fill_userinfo'        => '0',
            'teacher_modify_price'     => '1',
            'teacher_search_order'     => '0',
            'teacher_manage_student'   => '0',
            'teacher_export_student'   => '0',
            'student_download_media'   => '0',
            'free_course_nologin_view' => '1',
            'relatedCourses'           => '0',
            'coursesPrice'             => '0',
            'allowAnonymousPreview'    => '1',
            'live_course_enabled'      => '0',
            'userinfoFields'           => array(),
            "userinfoFieldNameArray"   => array(),
            "copy_enabled"             => '0'
        );

        $this->getSettingService()->set('course', $courseSetting);
        $courseSetting = array_merge($default, $courseSetting);

        if ($request->getMethod() == 'POST') {
            $courseSetting = $request->request->all();

            if (!isset($courseSetting['userinfoFields'])) {
                $courseSetting['userinfoFields'] = array();
            }

            if (!isset($courseSetting['userinfoFieldNameArray'])) {
                $courseSetting['userinfoFieldNameArray'] = array();
            }

            $courseSetting['live_student_capacity'] = empty($capacity['capacity']) ? 0 : $capacity['capacity'];

            $this->getSettingService()->set('course', $courseSetting);
            $this->getLogService()->info('system', 'update_settings', '更新课程设置', $courseSetting);
            $this->setFlashMessage('success', $this->trans('课程设置已保存！'));
        }

        $courseSetting['live_student_capacity'] = empty($capacity['capacity']) ? 0 : $capacity['capacity'];

        $userFields = $this->getUserFieldService()->getAllFieldsOrderBySeqAndEnabled();

        if ($courseSetting['userinfoFieldNameArray']) {
            foreach ($userFields as $key => $fieldValue) {
                if (!in_array($fieldValue['fieldName'], $courseSetting['userinfoFieldNameArray'])) {
                    $courseSetting['userinfoFieldNameArray'][] = $fieldValue['fieldName'];
                }
            }
        }

        return $this->render('TopxiaAdminBundle:System:course-setting.html.twig', array(
            'courseSetting' => $courseSetting,
            'capacity'      => $capacity,
            'userFields'    => $userFields,
            'capacity'      => $capacity
        ));
    }

    public function questionsSettingAction(Request $request)
    {
        $questionsSetting = $this->getSettingService()->get('questions', array());

        if (empty($questionsSetting)) {
            $default = array(
                'testpaper_answers_show_mode' => 'submitted'
            );
            $questionsSetting = $default;
        }

        if ($request->getMethod() == 'POST') {
            $questionsSetting = $request->request->all();
            $this->getSettingService()->set('questions', $questionsSetting);
            $this->getLogService()->info('system', 'questions_settings', '更新题库设置', $questionsSetting);
            $this->setFlashMessage('success', $this->trans('题库设置已保存！'));
        }

        return $this->render('TopxiaAdminBundle:System:questions-setting.html.twig');
    }

    public function adminSyncAction(Request $request)
    {
        $currentUser = $this->getCurrentUser();
        $setting     = $this->getSettingService()->get('user_partner', array());

        if (empty($setting['mode']) || !in_array($setting['mode'], array('phpwind', 'discuz'))) {
            return $this->createMessageResponse('info', $this->trans('未开启用户中心，不能同步管理员帐号！'));
        }

        $bind = $this->getUserService()->getUserBindByTypeAndUserId($setting['mode'], $currentUser['id']);

        if ($bind) {
            goto response;
        } else {
            $bind = null;
        }

        if ($request->getMethod() == 'POST') {
            $data        = $request->request->all();
            $partnerUser = $this->getAuthService()->checkPartnerLoginByNickname($data['nickname'], $data['password']);

            if (empty($partnerUser)) {
                $this->setFlashMessage('danger', $this->trans('用户名或密码不正确。'));
                goto response;
            } else {
                $this->getUserService()->changeEmail($currentUser['id'], $partnerUser['email']);
                $this->getUserService()->changeNickname($currentUser['id'], $partnerUser['nickname']);
                $this->getUserService()->changePassword($currentUser['id'], $data['password']);
                $this->getUserService()->bindUser($setting['mode'], $partnerUser['id'], $currentUser['id'], null);
                $user = $this->getUserService()->getUser($currentUser['id']);
                $this->authenticateUser($user);

                $this->setFlashMessage('success', $this->trans('管理员帐号同步成功。'));

                return $this->redirect($this->generateUrl('admin_setting_user_center'));
            }
        }

        response:
        return $this->render('TopxiaAdminBundle:System:admin-sync.html.twig', array(
            'mode' => $setting['mode'],
            'bind' => $bind
        ));
    }

    public function performanceAction(Request $request)
    {
        if ($request->getMethod() == 'POST') {
            $data = $request->request->all();
            $this->setFlashMessage('success', '设置成功');
            $this->getSettingService()->set('performance', $data);
            return $this->redirect($this->generateUrl('admin_performance'));
        }

        return $this->render('TopxiaAdminBundle:System:performance-setting.html.twig');
    }

    protected function getCourseService()
    {
        return $this->getServiceKernel()->createService('Course.CourseService');
    }

    protected function getFileService()
    {
        return $this->getServiceKernel()->createService('Content.FileService');
    }

    protected function getAppService()
    {
        return $this->getServiceKernel()->createService('CloudPlatform.AppService');
    }

    protected function getSettingService()
    {
        return $this->getServiceKernel()->createService('System.SettingService');
    }

    protected function getUserFieldService()
    {
        return $this->getServiceKernel()->createService('User.UserFieldService');
    }

    protected function getAuthService()
    {
        return $this->getServiceKernel()->createService('User.AuthService');
    }

    private function getWebExtension()
    {
        return $this->container->get('topxia.twig.web_extension');
    }
}
