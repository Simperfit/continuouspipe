<?php

namespace ContinuousPipe\Managed\ClusterCreation\GoogleCloud;

use ContinuousPipe\Google\ContainerEngineClusterRepository;
use ContinuousPipe\Google\GoogleException;
use ContinuousPipe\Managed\ClusterCreation\ClusterCreationException;
use ContinuousPipe\Managed\ClusterCreation\ClusterCreator;
use ContinuousPipe\Security\Account\GoogleAccount;
use ContinuousPipe\Security\Credentials\Cluster;
use ContinuousPipe\Security\Credentials\Cluster\Kubernetes;
use ContinuousPipe\Security\Team\Team;
use GuzzleHttp\ClientInterface;
use Ramsey\Uuid\Uuid;

class GKEClusterAccountCreator implements ClusterCreator
{
    /**
     * @var \Google_Client
     */
    private $googleClient;

    /**
     * @var ContainerEngineClusterRepository
     */
    private $containerEngineClusterRepository;

    /**
     * @var string
     */
    private $projectId;

    /**
     * @var string
     */
    private $serviceAccountFilePath;

    /**
     * @var string
     */
    private $sharedClusterIdentifier;

    public function __construct(
        ContainerEngineClusterRepository $containerEngineClusterRepository,
        string $serviceAccountFilePath,
        string $projectId,
        string $sharedClusterIdentifier
    ) {
        $this->containerEngineClusterRepository = $containerEngineClusterRepository;
        $this->projectId = $projectId;
        $this->serviceAccountFilePath = $serviceAccountFilePath;
        $this->sharedClusterIdentifier = $sharedClusterIdentifier;

        $this->googleClient = new \Google_Client();
        $this->googleClient->setAuthConfig($serviceAccountFilePath);
        $this->googleClient->setScopes(array(
            'https://www.googleapis.com/auth/cloud-platform',
            'https://www.googleapis.com/auth/compute.readonly'
        ));
    }

    /**
     * {@inheritdoc}
     */
    public function createForTeam(Team $team, string $clusterIdentifier): Cluster
    {
        try {
            $base64EncodedServiceAccount = $this->getTeamUserServiceAccount($team);
        } catch (\Google_Exception $e) {
            throw new ClusterCreationException('Was not able to get service account from Google Cloud: '.$e->getMessage(), $e->getCode(), $e);
        }

        try {
            $cluster = $this->containerEngineClusterRepository->find(
                new GoogleAccount(
                    Uuid::uuid4()->toString(),
                    'managed-template',
                    'email@example.com',
                    null,
                    null,
                    null,
                    base64_encode(file_get_contents($this->serviceAccountFilePath))
                ),
                $this->projectId,
                $this->sharedClusterIdentifier
            );
        } catch (GoogleException $e) {
            throw new ClusterCreationException('Can\'t get cluster from GKE API', $e->getCode(), $e);
        }

        return new Kubernetes(
            $clusterIdentifier,
            $this->endpoint($cluster->getEndpoint()),
            $this->version($cluster->getCurrentMasterVersion()),
            null,
            null,
            [],
            null,
            null, // (Don't add the CA certificate for now - https://inviqa.atlassian.net/browse/CD-599) $cluster->getMasterAuthentication()->getClusterCaCertificate(),
            $base64EncodedServiceAccount,
            new Cluster\ClusterCredentials(
                $cluster->getMasterAuthentication()->getUsername(),
                $cluster->getMasterAuthentication()->getPassword()
                // (Don't use client certificate for now - https://inviqa.atlassian.net/browse/CD-606) $cluster->getMasterAuthentication()->getClientCertificate()
            )
        );
    }

    /**
     * Create a base64-encoded service account.
     *
     * @param Team $team
     *
     * @throws \Google_Service_Exception
     *
     * @return string
     */
    private function getTeamUserServiceAccount(Team $team) : string
    {
        $iam = new \Google_Service_Iam($this->googleClient);

        $projectFullName = 'projects/'.$this->projectId;
        $accountId = 'team-'.$team->getSlug();
        $serviceAccountName = $accountId.'@'.$this->projectId.'.iam.gserviceaccount.com';
        $serviceAccountFullName = $projectFullName.'/serviceAccounts/'.$serviceAccountName;

        try {
            $iam->projects_serviceAccounts->get($serviceAccountFullName);
        } catch (\Google_Service_Exception $e) {
            if ($e->getCode() == 404) {
                $iam->projects_serviceAccounts->create('projects/' . $this->projectId, new \Google_Service_Iam_CreateServiceAccountRequest([
                    'accountId' => $accountId,
                    'serviceAccount' => new \Google_Service_Iam_ServiceAccount([
                        'displayName' => 'Team "'.$team->getSlug().'"',
                    ]),
                ]));
            } else {
                throw $e;
            }
        }

        // Create a service account key (as we can't retrieve one, anyway)
        $serviceAccountKey = $iam->projects_serviceAccounts_keys->create($serviceAccountFullName, new \Google_Service_Iam_CreateServiceAccountKeyRequest([
            'privateKeyType' => 'TYPE_GOOGLE_CREDENTIALS_FILE',
        ]));

        return $serviceAccountKey->privateKeyData;
    }

    private function endpoint(string $endpoint) : string
    {
        return 'https://'.$endpoint;
    }

    private function version(string $version) : string
    {
        return 'v'.$version;
    }
}