<?php

namespace Octo\FacebookIdentity\Admin\Controller;

use b8\Config;
use b8\Form\Element\Checkbox;
use b8\Form\Element\Submit;
use b8\Form\Element\Text;
use b8\Form\FieldSet;
use Facebook\Facebook;
use Octo\Admin\Controller;
use Octo\Admin\Menu;
use Octo\Admin\Form as FormElement;
use Octo\Form\Element\OnOffSwitch;
use Octo\Store;
use Octo\System\Model\Setting;
use Octo\System\Model\User;

class FacebookIdentityController extends Controller
{
    public static function registerMenus(Menu $menu)
    {
        $root = $menu->getRoot('Developer');
        $root->addChild(new Menu\Item('Facebook Identity Settings', '/facebook-identity/settings'));
    }

    public function login()
    {
        $appId = Setting::get('facebook-identity', 'app_id');
        $appSecret = Setting::get('facebook-identity', 'app_secret');

        $facebook = new Facebook([
            'app_id' => $appId,
            'app_secret' => $appSecret,
            'default_graph_version' => 'v2.6',
        ]);

        $helper = $facebook->getRedirectLoginHelper();

        $token = $helper->getAccessToken();

        $oauth = $facebook->getOAuth2Client();

        if (!$token->isLongLived()) {
            $token = $oauth->getLongLivedAccessToken($token);
        }

        Setting::set('facebook-identity', 'access_token', (string)$token);

        return $this->redirect('/facebook-identity/settings');
    }

    public function settings()
    {
        $values = Setting::getForScope('facebook-identity');
        $form = $this->settingsForm($values);

        if ($this->request->getMethod() == 'POST') {
            $params = $this->getParams();
            $form->setValues($params);

            Setting::setForScope('facebook-identity', $form->getValues());
            $this->successMessage('Settings saved successfully.');
        } else {
            $form->setValues($values);
        }

        $this->view->form = $form;
        $appId = Setting::get('facebook-identity', 'app_id');
        $appSecret = Setting::get('facebook-identity', 'app_secret');
        $token = Setting::get('facebook-identity', 'access_token');

        if (!empty($appId) && !empty($appSecret)) {
            $facebook = new Facebook([
                'app_id' => $appId,
                'app_secret' => $appSecret,
                'default_graph_version' => 'v2.6',
            ]);

            $helper = $facebook->getRedirectLoginHelper();
            $permissions = ['email', 'publish_actions', 'user_managed_groups'];
            $redirect = Config::getInstance()->get('site.url') . '/manage/facebook-identity/login';

            $this->view->loginUrl = $helper->getLoginUrl($redirect, $permissions);

            if (!empty($token)) {
                try {
                    $response = $facebook->get('/me', $token);
                    $this->view->fbUser = $response->getGraphUser()->getName();
                } catch (\Exception $ex) {

                }
            }
        }
    }

    protected function settingsForm($values)
    {
        $form = new FormElement();
        $form->setMethod('POST');

        $fieldset = new FieldSet();
        $fieldset->setId('oauth');
        $fieldset->setLabel('OAuth Details');
        $form->addField($fieldset);

        $fieldset->addField(Text::create('app_id', 'App ID'));
        $fieldset->addField(Text::create('app_secret', 'App Secret'));

        $submit = new Submit();
        $submit->setValue('Save Settings');
        $form->addField($submit);

        return $form;
    }
}
