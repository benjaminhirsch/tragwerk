<?php

declare(strict_types=1);

namespace Tragwerk\Application\Cli\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Tragwerk\Application\Service\Credential\CredentialEncryptor;
use Tragwerk\Domain\Entity\Credential;
use Tragwerk\Domain\Repository\CredentialRepository;

use function assert;
use function sprintf;
use function str_starts_with;
use function trim;

#[AsCommand(
    name: 'credential:encrypt-keys',
    description: 'Encrypt any plaintext SSH private keys stored in the credentials table (idempotent)',
)]
final class EncryptCredentialKeysCommand extends Command
{
    public function __construct(
        private readonly CredentialRepository $credentialRepository,
        private readonly CredentialEncryptor $credentialEncryptor,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $encrypted = 0;
        $skipped   = 0;

        foreach ($this->credentialRepository->getAll() as $credential) {
            assert($credential instanceof Credential);

            $key = $credential->privateKey;

            // Plaintext PEM/OpenSSH keys start with "-----BEGIN"; ciphertext (base64
            // of nonce+secretbox) never does. Anything else is already encrypted.
            if ($key === null || ! str_starts_with(trim($key), '-----BEGIN')) {
                $skipped++;

                continue;
            }

            $credential->privateKey = $this->credentialEncryptor->encrypt($key);
            $this->credentialRepository->update($credential);
            $encrypted++;
        }

        $output->writeln(sprintf('Encrypted %d key(s), skipped %d already-encrypted/empty.', $encrypted, $skipped));

        return Command::SUCCESS;
    }
}
