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

    public function info()
    {
        try {
            $appId = Setting::getSetting('facebook-identity', 'app_id');
            $appSecret = Setting::getSetting('facebook-identity', 'app_secret');
            $token = Setting::getSetting('facebook-identity', 'access_token');

            $facebook = new Facebook([
                'app_id' => $appId,
                'app_secret' => $appSecret,
                'default_graph_version' => 'v2.8',
            ]);

            $response = $facebook->get('/me?fields=id,name,email,picture', $token);
            $user = $response->getGraphUser();

            return $this->json([
                'success' => true,
                'name' => $user['name'],
                'email' => $user['email'],
                'photo' => $user['picture']['url'],
            ]);
        } catch (\Exception $ex) {
            return $this->json([
                'success' => false,
                'error' => $ex->getMessage(),
            ]);
        }
    }

    public function logout()
    {
        Setting::set('facebook-identity', 'access_token', null);
        return $this->info();
    }

    public function login()
    {
        $appId = Setting::getSetting('facebook-identity', 'app_id');
        $appSecret = Setting::getSetting('facebook-identity', 'app_secret');

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
        $this->setTitle('Facebook Identity');

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

        $this->template->form = $form;
        $appId = Setting::getSetting('facebook-identity', 'app_id');
        $appSecret = Setting::getSetting('facebook-identity', 'app_secret');
        $token = Setting::getSetting('facebook-identity', 'access_token');

        if (!empty($appId) && !empty($appSecret)) {
            $facebook = new Facebook([
                'app_id' => $appId,
                'app_secret' => $appSecret,
                'default_graph_version' => 'v2.6',
            ]);

            $helper = $facebook->getRedirectLoginHelper();
            $permissions = ['email', 'publish_actions', 'user_managed_groups'];
            $redirect = Config::getInstance()->get('site.url') . '/manage/facebook-identity/login';

            $this->template->loginUrl = $helper->getLoginUrl($redirect, $permissions);

            if (!empty($token)) {
                try {
                    $response = $facebook->get('/me', $token);
                    $this->template->fbUser = $response->getGraphUser()->getName();
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
