<?php

namespace SolidAffiliate\Lib\VO\AffiliatePortal;

use SolidAffiliate\Lib\VO\Schema;

/**
 *
 * @psalm-type AffiliatePortalRegistrationViewInterfaceType = array{
 *   form_values: array<string, mixed>,
 *   schema: Schema<string>,
 *   form_nonce: string,
 *   submit_action: string,
 *   affiliate_approval_required: boolean,
 *   notices: string,
 *   just_submitted: boolean
 * }
 */
class AffiliatePortalRegistrationViewInterface
{
    /** @var AffiliatePortalRegistrationViewInterfaceType $data */
    public $data;

    /** @var array<string, mixed> $form_values */
    public $form_values;

    /** @var Schema<string> $schema */
    public $schema;

    /** @var string $form_nonce */
    public $form_nonce;

    /** @var string $submit_action */
    public $submit_action;

    /** @var boolean $affiliate_approval_required */
    public $affiliate_approval_required;

    /** @var string $notices */
    public $notices;

    /** @var boolean $just_submitted */
    public $just_submitted;

    /** @param AffiliatePortalRegistrationViewInterfaceType $data */
    public function __construct($data)
    {
        $this->data = $data;

        $this->form_values = $data['form_values'];
        $this->schema = $data['schema'];
        $this->form_nonce = $data['form_nonce'];
        $this->submit_action = $data['submit_action'];
        $this->affiliate_approval_required = $data['affiliate_approval_required'];
        $this->notices = $data['notices'];
        $this->just_submitted = $data['just_submitted'];
    }
}
