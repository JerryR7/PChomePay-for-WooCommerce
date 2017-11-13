<?php
/**
 * Created by PhpStorm.
 * User: Jerry
 * Date: 2017/11/13
 * Time: 下午3:44
 */

class ApiException
{
    // auth error
    const INVALID_USER_OR_PASS = 10001;
    const INVALID_SERVER_IP = 10002;
    const TOKEN_INVALID = 10003;
    const TOKEN_EXPIRED = 10004;
    const API_CLIENT_NOT_OPEN_YET = 10005;

    // invalid request error
    const ORDER_DUPLICATE = 20001;
    const ORDER_NOT_EXIST = 20002;
    const PAY_TYPE_NOT_SUPPORT = 20003;
    const MEMBER_NOT_EXIST = 20004;
    const INVALID_PARAMS = 20005;
    const INSUFFICIENT_FUNDS = 20006;
    const CHECKING_DATE_INVALID = 20007;
    const ORDER_ITEMS_TOO_LONG = 20008;

    // pay error
    const INVALID_ATM_EXPIRE_DATE = 40001;
    const ORDER_LIMIT_EXCEED = 40002;

    // refund error
    const REFUND_DUPLICATE = 50001;
    const ORDER_NOT_CONFIRM = 50002;
    const ORDER_NOT_FOUND = 50003;
    const AMOUNT_FEE_INVALID = 50004;
    const BALANCE_NOT_ENOUGH = 50005;
    const REFUND_NOT_FOUND = 50006;
    const INSTALLMENT_CANT_PARTIALLY_REFUND = 50007;
    const COVER_TRANSFEE_MUST_SET_WHILE_ATM_EACH = 50008;
    const ATM_REFUND_NOT_READY = 50009;

    // withdraw error
    const BASE_CODE = 70000;
    const WITHDRAW_LESS_THAN_10 = 70001;
    const MEMBER_VERIFY_NOT_COMPLETE = 70002;
    const WITHDRAW_BIGGER_THAN_BALANCE = 70003;
    const WITHDRAW_BIGGER_THAN_DAILY_LIMIT = 70004;
    const WITHDRAW_BANK_ACCT_NOT_SET = 70005;

    // audit error
    const INVALID_STATUS = 80001;

    public function getApiCode()
    {
        return 401;
    }

    public function getApiType($code)
    {
        $errorType = [
            '1' => 'auth error',
            '2' => 'invalid request error',
            '4' => 'pay error',
            '5' => 'refund error',
            '7' => 'withdraw error',
            '8' => 'audit error',
        ];

        return ($errorType[substr($code, 0, 1)]);

    }

    public function getErrMsg($code)
    {
        $msg = [
            // auth error
            static::INVALID_SERVER_IP => "Server IP not allow",
            static::INVALID_USER_OR_PASS => "invalid user password",
            static::TOKEN_INVALID => "invalid token",
            static::TOKEN_EXPIRED => "token expired",
            static::API_CLIENT_NOT_OPEN_YET => "api client not open yet",

            // invalid request error
            static::ORDER_DUPLICATE => "order id duplicate",
            static::ORDER_NOT_EXIST => "order not exists",
            static::PAY_TYPE_NOT_SUPPORT => "pay type not support",
            static::MEMBER_NOT_EXIST => "member id doesn't exists",
            static::INVALID_PARAMS => "params is not valid",
            static::INSUFFICIENT_FUNDS => "When the credit card installments, the amount of orders can not be less than 30",
            static::CHECKING_DATE_INVALID => "It not allow to check today's data",
            static::ORDER_ITEMS_TOO_LONG => "order items string too long",

            // pay error
            static::INVALID_ATM_EXPIRE_DATE => "invalid atm expire date",
            static::ORDER_LIMIT_EXCEED => "order limit exceed",

            // refund error
            static::REFUND_DUPLICATE => "Refund id is duplicate.",
            static::ORDER_NOT_CONFIRM => "The order is not confirm yet, refund can't be execute",
            static::ORDER_NOT_FOUND => "The order is not found, order id might invalid",
            static::AMOUNT_FEE_INVALID => "Refund amount must bigger than 0",
            static::BALANCE_NOT_ENOUGH => "Your balance is not enough to refund",
            static::REFUND_NOT_FOUND => "Can not find the information of the refund id",
            static::INSTALLMENT_CANT_PARTIALLY_REFUND => "Order payed by credit card with installment can only refund with full order amount only.",
            static::COVER_TRANSFEE_MUST_SET_WHILE_ATM_EACH => "cover_transfee must be set if the order payed by ATM or EACH",
            static::ATM_REFUND_NOT_READY => "The atm refund data is not ready. Please send the refund request later",

            // withdraw error
            static::WITHDRAW_LESS_THAN_10 => "withdraw must be bigger than 10 dollars",
            static::MEMBER_VERIFY_NOT_COMPLETE => "member verify not complete",
            static::WITHDRAW_BIGGER_THAN_BALANCE => "withdraw amount is over than available balance",
            static::WITHDRAW_BIGGER_THAN_DAILY_LIMIT => "withdraw amount is over than withdraw daily limit",
            static::WITHDRAW_BANK_ACCT_NOT_SET => "information of bank to withdraw is not set yet",

            // audit error
            static::INVALID_STATUS => "invalid status to do this operation",
        ];

        return $msg[$code];
    }
}