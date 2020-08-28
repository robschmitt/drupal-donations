<?php
namespace Drupal\lane_client_api;

use Drupal\lane_donations\Entity\Donation;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\RequestOptions;
use Drupal\Core\Logger\LoggerChannelTrait;

/**
 * Class CLIENT_API
 * @package Drupal\lane_client_api
 */
class CLIENT_API {

  use LoggerChannelTrait;

  /**
   * GuzzleHttp\ClientInterface definition.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $http_client;

  /**
   * @var string The endpoint URL prefix
   */
  protected $endpoint_prefix;

  /**
   * @var string The username used to connect to the API
   */
  protected $username;

  /**
   * @var string The password used to connect to the API
   */
  protected $password;

  /**
   * @var string The last message returned by the API
   */
  protected $message;

  /**
   * CLIENT_API constructor.
   * @param ClientInterface $http_client
   * @throws \Exception
   */
  public function __construct(ClientInterface $http_client)
  {

    $this->http_client = $http_client;

    $endpoint_prefix = getenv('CLIENT_API_ENDPOINT_PREFIX');
    if (!$endpoint_prefix) {
      throw new \Exception('Endpoint prefix not set.');
    }

    $username = getenv('CLIENT_API_USERNAME');
    if (!$username) {
      throw new \Exception('API username not set.');
    }

    $password = getenv('CLIENT_API_PASSWORD');
    if (!$password) {
      throw new \Exception('API password not set.');
    }

    $this->endpoint_prefix = $endpoint_prefix;
    $this->username = $username;
    $this->password = $password;

  }

  public function createContact(array $data)
  {

    $response = $this->call('contact', 'POST', $data);

    if (!empty($response->data->CONTACTID)) {
      return $response->data->CONTACTID;
    }

    return false;

  }

  public function addDonation(Donation $donation)
  {

    switch ($donation->field_donation_type->value) {
      case 'recurring':
        return $this->addRecurringDonation($donation);
        break;
      case 'single':
      case 'fundraiser':
        return $this->addSingleDonation($donation);
        break;
      case 'sponsor':
        return $this->addSponsorASpace($donation);
        break;
    }

  }

  protected function addSingleDonation(Donation $donation)
  {
    $donation_type = $donation->singleDonationType();
    $description = $donation->field_description->value;
    if ($donation_type == 'raisedfunds') {
      $description = $donation->field_fundraising_source->value;
    }
    $data = [
      'contact_id' => $donation->field_contact_id->value,
      'donation_date' => date('d/m/Y', $donation->getCreatedTime()),
      'donation_type' => $donation_type,
      'appeal' => $donation->field_appeal->value,
      'amount' => $donation->field_donation_amount->value / 100,
      'description' => $description,
    ];
    return $this->call('donation', 'POST', $data);
  }

  protected function addRecurringDonation(Donation $donation)
  {
    $data = [
      'contact_id' => $donation->field_contact_id->value,
      'donation_date' => date('d/m/Y', $donation->getCreatedTime()),
      'appeal' => $donation->field_appeal->value,
      'amount' => $donation->field_donation_amount->value / 100,
      'day_of_month' => $donation->field_day_of_month->value ? $donation->field_day_of_month->value : 1,
      'account_name' => $donation->field_account_name->value,
      'account_number' => $donation->field_account_number->value,
      'sort_code' => str_replace('-', '', $donation->field_sort_code->value),
      'description' => $donation->field_description->value,
    ];
    return $this->call('donation/recurring', 'POST', $data);
  }

  protected function addSponsorASpace(Donation $donation)
  {
    $data = [
      'contact_id' => $donation->field_contact_id->value,
      'donation_date' => date('d/m/Y', $donation->getCreatedTime()),
      'appeal' => $donation->field_appeal->value,
      'amount' => $donation->field_donation_amount->value / 100,
      'arrc_id' => $donation->field_arrc_id->value,
      'space_type' => $donation->field_space_type->value,
      'is_gift' => $donation->field_is_gift->value ? 'Y' : 'N',
      'message' => $donation->field_message->value,
      'recipient_first_name' => $donation->field_recipient_first_name->value,
      'recipient_surname' => $donation->field_recipient_surname->value,
      'recipient_address_line1' => $donation->field_recipient_address_line1->value,
      'recipient_address_line2' => $donation->field_recipient_address_line2->value,
      'recipient_town' => $donation->field_recipient_town->value,
      'recipient_postcode' => $donation->field_recipient_postcode->value,
      'recipient_county' => $donation->field_recipient_county->value,
      'recipient_country' => $donation->field_recipient_country->value,
    ];
    return $this->call('sponsor', 'POST', $data);
  }

  public function rehome($data)
  {
    return $this->call('rehome', 'POST', $data);
  }

  /**
   * @param $action
   * @param $method
   * @param $data
   * @return bool|mixed
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  protected function call($action, $method, $data)
  {

    $uri = $this->endpoint_prefix . $action;

    try {
      $response = $this->http_client->request($method, $uri, [
        RequestOptions::VERIFY => false,
        'headers' => $this->getBasicRequestHeaders(),
        RequestOptions::JSON => $data,
        RequestOptions::TIMEOUT => 10, // Wait no more than 10 seconds
      ]);
      $this->logSuccess('Successfully called @endpoint', [
        '@endpoint' => $action,
      ]);
      return \json_decode($response->getBody()->getContents());
    }
    catch (\Exception $e) {
      $this->message = $e->getMessage();
      $this->logError($this->message);
      return false;
    }

  }

  public function getLastMessage()
  {
    return $this->message;
  }

  protected function getBasicRequestHeaders() {
    $credentials = \base64_encode("{$this->username}:{$this->password}");
    return [
      'Content-Type' => 'application/json',
      "Accept" => "application/json",
      'Authorization' => "Basic " . $credentials,
    ];
  }

  protected function logSuccess($message, $vars = []) {
    $this->getLogger('CLIENT_API')->info($message, $vars);
  }

  protected function logError($message, $vars = []) {
    $this->getLogger('CLIENT_API')->error($message, $vars);
  }

}
