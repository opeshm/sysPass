<?php
/**
 * sysPass
 *
 * @author    nuxsmin
 * @link      http://syspass.org
 * @copyright 2012-2018, Rubén Domínguez nuxsmin@$syspass.org
 *
 * This file is part of sysPass.
 *
 * sysPass is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * sysPass is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 *  along with sysPass.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace SP\Services\Account;

use Defuse\Crypto\Exception\CryptoException;
use SP\Account\AccountAcl;
use SP\Account\AccountRequest;
use SP\Core\Acl\Acl;
use SP\Core\Acl\ActionsInterface;
use SP\Core\Crypt\Crypt;
use SP\Core\Exceptions\QueryException;
use SP\Core\Exceptions\SPException;
use SP\Core\Traits\InjectableTrait;
use SP\DataModel\Dto\AccountDetailsResponse;
use SP\Log\Log;
use SP\Repositories\Account\AccountHistoryRepository;
use SP\Repositories\Account\AccountRepository;
use SP\Repositories\Account\AccountToTagRepository;
use SP\Repositories\Account\AccountToUserGroupRepository;
use SP\Repositories\Account\AccountToUserRepository;
use SP\Core\Crypt\Session as CryptSession;
use SP\Services\ServiceItemTrait;

/**
 * Class AccountService
 *
 * @package SP\Services\Account
 */
class AccountService implements AccountServiceInterface
{
    use InjectableTrait;
    use ServiceItemTrait;

    /**
     * @var AccountRepository
     */
    protected $accountRepository;
    /**
     * @var AccountToUserGroupRepository
     */
    protected $accountToUserGroupRepository;
    /**
     * @var AccountToUserRepository
     */
    protected $accountToUserRepository;
    /**
     * @var AccountToTagRepository
     */
    protected $accountToTagRepository;

    /**
     * AccountService constructor.
     *
     * @throws \SP\Core\Dic\ContainerException
     */
    public function __construct()
    {
        $this->injectDependencies();
    }

    /**
     * @param AccountRepository            $accountRepository
     * @param AccountToUserGroupRepository $accountToUserGroupRepository
     * @param AccountToUserRepository      $accountToUserRepository
     * @param AccountToTagRepository       $accountToTagRepository
     */
    public function inject(AccountRepository $accountRepository,
                           AccountToUserGroupRepository $accountToUserGroupRepository,
                           AccountToUserRepository $accountToUserRepository,
                           AccountToTagRepository $accountToTagRepository)
    {
        $this->accountRepository = $accountRepository;
        $this->accountToUserGroupRepository = $accountToUserGroupRepository;
        $this->accountToUserRepository = $accountToUserRepository;
        $this->accountToTagRepository = $accountToTagRepository;
    }

    /**
     * @param int $id
     * @return AccountDetailsResponse
     * @throws \SP\Core\Exceptions\SPException
     */
    public function getById($id)
    {
        return new AccountDetailsResponse($id, $this->accountRepository->getById($id));
    }

    /**
     * @param AccountDetailsResponse $accountDetailsResponse
     * @return AccountService
     */
    public function withUsersById(AccountDetailsResponse $accountDetailsResponse)
    {
        $accountDetailsResponse->setUsers($this->accountToUserRepository->getUsersByAccountId($accountDetailsResponse->getId()));

        return $this;
    }

    /**
     * @param AccountDetailsResponse $accountDetailsResponse
     * @return AccountService
     */
    public function withUserGroupsById(AccountDetailsResponse $accountDetailsResponse)
    {
        $accountDetailsResponse->setUserGroups($this->accountToUserGroupRepository->getUserGroupsByAccountId($accountDetailsResponse->getId()));

        return $this;
    }

    /**
     * @param AccountDetailsResponse $accountDetailsResponse
     * @return AccountService
     */
    public function withTagsById(AccountDetailsResponse $accountDetailsResponse)
    {
        $accountDetailsResponse->setTags($this->accountToTagRepository->getTagsByAccountId($accountDetailsResponse->getId()));

        return $this;
    }

    /**
     * @param $id
     * @return bool
     * @throws QueryException
     * @throws \SP\Core\Exceptions\ConstraintException
     */
    public function incrementViewCounter($id)
    {
        return $this->accountRepository->incrementViewCounter($id);
    }

    /**
     * @param $id
     * @return bool
     * @throws QueryException
     * @throws \SP\Core\Exceptions\ConstraintException
     */
    public function incrementDecryptCounter($id)
    {
        return $this->accountRepository->incrementDecryptCounter($id);
    }

    /**
     * @param $id
     * @return \SP\DataModel\AccountPassData
     */
    public function getPasswordForId($id)
    {
        return $this->accountRepository->getPasswordForId($id);
    }

    /**
     * @param AccountRequest $accountRequest
     * @return int
     * @throws QueryException
     * @throws SPException
     * @throws \SP\Core\Exceptions\ConstraintException
     */
    public function create(AccountRequest $accountRequest)
    {
        $accountRequest->changePermissions = (new AccountAcl(ActionsInterface::ACCOUNT_EDIT))->isShowPermission();

        $pass = $this->getPasswordEncrypted($accountRequest->pass);

        $accountRequest->pass = $pass['pass'];
        $accountRequest->key = $pass['key'];

        $id = $this->accountRepository->create($accountRequest);

        $this->addItems($accountRequest, $id);

        return $id;
    }

    /**
     * Devolver los datos de la clave encriptados
     *
     * @param string $pass
     * @param string $masterPass Clave maestra a utilizar
     * @return array
     * @throws QueryException
     * @throws SPException
     */
    public function getPasswordEncrypted($pass, $masterPass = null)
    {
        try {
            $masterPass = $masterPass ?: CryptSession::getSessionKey();

            $out['key'] = Crypt::makeSecuredKey($masterPass);
            $out['pass'] = Crypt::encrypt($pass, $out['key'], $masterPass);

            if (strlen($pass) > 1000 || strlen($out['key']) > 1000) {
                throw new QueryException(SPException::SP_ERROR, __u('Error interno'));
            }

            return $out;
        } catch (CryptoException $e) {
            throw new SPException(SPException::SP_ERROR, __u('Error interno'));
        }
    }

    /**
     * Adds external items to the account
     *
     * @param AccountRequest $accountRequest
     * @param int            $accountId
     */
    protected function addItems(AccountRequest $accountRequest, $accountId)
    {
        try {
            if ($accountRequest->changePermissions) {
                if (is_array($accountRequest->userGroups) && !empty($accountRequest->userGroups)) {
                    $this->accountToUserGroupRepository->add($accountRequest);
                }

                if (is_array($accountRequest->users) && !empty($accountRequest->users)) {
                    $this->accountToUserRepository->add($accountRequest);
                }
            }

            if (is_array($accountRequest->tags) && !empty($accountRequest->tags)) {
                $this->accountToTagRepository->add($accountRequest);
            }
        } catch (SPException $e) {
            Log::writeNewLog(__FUNCTION__, $e->getMessage(), Log::ERROR);
        }
    }

    /**
     * Updates external items for the account
     *
     * @param AccountRequest $accountRequest
     * @throws SPException
     */
    public function update(AccountRequest $accountRequest)
    {
        $accountRequest->changePermissions = (new AccountAcl(ActionsInterface::ACCOUNT_EDIT))->isShowPermission();

        $this->addHistory($accountRequest->id);

        $this->accountRepository->update($accountRequest);

        $this->updateItems($accountRequest);
    }

    /**
     * @param int  $accountId
     * @param bool $isDelete
     * @throws QueryException
     * @throws \SP\Core\Exceptions\ConstraintException
     */
    protected function addHistory($accountId, $isDelete = false)
    {
        $accountHistoryRepository = new AccountHistoryRepository();
        $accountHistoryRepository->create([
            'id' => $accountId,
            'isDelete' => $isDelete
        ]);
    }

    /**
     * Updates external items for the account
     *
     * @param AccountRequest $accountRequest
     */
    protected function updateItems(AccountRequest $accountRequest)
    {
        try {
            if ($accountRequest->changePermissions) {
                $this->accountToUserGroupRepository->update($accountRequest);
                $this->accountToUserRepository->update($accountRequest);
            }

            $this->accountToTagRepository->update($accountRequest);
        } catch (SPException $e) {
            Log::writeNewLog(__FUNCTION__, $e->getMessage(), Log::ERROR);
        }
    }

    /**
     * @param AccountRequest $accountRequest
     * @throws QueryException
     * @throws SPException
     * @throws \SP\Core\Exceptions\ConstraintException
     */
    public function editPassword(AccountRequest $accountRequest)
    {
        $this->addHistory($accountRequest->id);

        $pass = $this->getPasswordEncrypted($accountRequest->pass);

        $accountRequest->pass = $pass['pass'];
        $accountRequest->key = $pass['key'];

        $this->accountRepository->editPassword($accountRequest);
    }

    /**
     * @param $historyId
     * @param $accountId
     * @throws QueryException
     * @throws \SP\Core\Exceptions\ConstraintException
     */
    public function editRestore($historyId, $accountId)
    {
        $this->addHistory($accountId);

        $this->accountRepository->editRestore($historyId);
    }

    /**
     * @param $id
     * @return bool
     * @throws SPException
     */
    public function delete($id)
    {
        return $this->accountRepository->delete($id);
    }

    /**
     * Logs account action
     *
     * @param int $id
     * @param int $actionId
     * @return \SP\Core\Messages\LogMessage
     * @throws SPException
     */
    public function logAction($id, $actionId)
    {
        $account = $this->accountRepository->getById($id);

        $Log = new Log();
        $LogMessage = $Log->getLogMessage();
        $LogMessage->setAction(Acl::getActionInfo($actionId));
        $LogMessage->addDetails(__u('ID'), $id);
        $LogMessage->addDetails(__u('Cuenta'), $account->getClientName() . ' / ' . $account->getName());
        $Log->writeLog();

        return $LogMessage;
    }

    /**
     * @param $accountId
     * @return array
     */
    public function getForUser($accountId)
    {
        return $this->accountRepository->getForUser($accountId);
    }

    /**
     * @param $accountId
     * @return array
     */
    public function getLinked($accountId)
    {
        return $this->accountRepository->getLinked($accountId);
    }

    /**
     * Returns all the items mapping fields for a select type element (id and name fields)
     */
    public function getAllItemsForSelect()
    {
        return $this->getItemsForSelect($this->accountRepository);
    }

    /**
     * @param $id
     * @return \SP\DataModel\AccountPassData
     */
    public function getPasswordHistoryForId($id)
    {
        return $this->accountRepository->getPasswordHistoryForId($id);
    }
}