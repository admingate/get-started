<?php

namespace Admingate\GetStarted\Http\Controllers;

use Admingate\ACL\Models\User;
use Admingate\ACL\Repositories\Interfaces\UserInterface;
use Admingate\Base\Events\UpdatedContentEvent;
use Admingate\Base\Http\Controllers\BaseController;
use Admingate\Base\Http\Responses\BaseHttpResponse;
use Admingate\GetStarted\Http\Requests\GetStartedRequest;
use Illuminate\Support\Facades\Hash;
use ThemeOption;

class GetStartedController extends BaseController
{
    public function save(GetStartedRequest $request, BaseHttpResponse $response): BaseHttpResponse
    {
        $step = $request->input('step');

        $nextStep = $step + 1;

        switch ($step) {
            case 1:
                break;
            case 2:
                if (! theme_option()->hasField('primary_color')) {
                    $request->request->remove('primary_color');
                }

                if (! theme_option()->hasField('primary_font')) {
                    $request->request->remove('primary_font');
                }

                foreach ($request->except(['_token', 'step']) as $key => $value) {
                    if ($value === null) {
                        continue;
                    }

                    if (is_array($value)) {
                        $value = json_encode($value);
                    }

                    ThemeOption::setOption($key, $value);

                    if (in_array($key, ['admin_logo', 'admin_favicon'])) {
                        setting()->set($key, $value);
                    }
                }

                ThemeOption::saveOptions();

                setting()->save();

                /**
                 * @var User $user
                 */
                $user = auth()->user();

                if ($user->username != config('core.base.general.demo.account.username', 'admingate') &&
                    ! Hash::check($user->getAuthPassword(), config('core.base.general.demo.account.password', '159357'))
                ) {
                    $nextStep = 4;
                }

                break;
            case 3:
                /**
                 * @var User $user
                 */
                $user = auth()->user();

                $userRepository = app(UserInterface::class);

                if ($user->email !== $request->input('email')) {
                    $users = $userRepository
                        ->getModel()
                        ->where('email', $request->input('email'))
                        ->where('id', '<>', $user->id)
                        ->exists();

                    if ($users) {
                        return $response
                            ->setError()
                            ->setMessage(trans('core/acl::users.email_exist'))
                            ->withInput();
                    }
                }

                if ($user->username !== $request->input('username')) {
                    $users = $userRepository
                        ->getModel()
                        ->where('username', $request->input('username'))
                        ->where('id', '<>', $user->id)
                        ->exists();

                    if ($users) {
                        return $response
                            ->setError()
                            ->setMessage(trans('core/acl::users.username_exist'))
                            ->withInput();
                    }
                }

                $user->fill($request->only(['username', 'email']));
                $user->password = Hash::make($request->input('password'));

                $userRepository->createOrUpdate($user);

                do_action(USER_ACTION_AFTER_UPDATE_PROFILE, USER_MODULE_SCREEN_NAME, $request, $user);
                do_action(USER_ACTION_AFTER_UPDATE_PASSWORD, USER_MODULE_SCREEN_NAME, $request, $user);

                event(new UpdatedContentEvent(USER_MODULE_SCREEN_NAME, $request, $user));

                break;
            case 4:
                setting()->set('is_completed_get_started', '1')->save();

                break;
        }

        return $response->setData(['step' => $nextStep]);
    }
}
