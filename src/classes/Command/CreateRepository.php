<?php

namespace WPSnapshots\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Question\Question;
use WPSnapshots\Connection;
use \WPSnapshots\Utils;

/**
 * The create-repository command creates the wpsnapshots bucket in the provided
 * S3 repository and the table within DynamoDB. If the bucket or table already exists,
 * the command does nothing.
 */
class CreateRepository extends Command {

	/**
	 * Setup up command
	 */
	protected function configure() {
		$this->setName( 'create-repository' );
		$this->setDescription( 'Create new WP Snapshots repository.' );
	}

	/**
	 * Executes the command
	 *
	 * @param  InputInterface  $input
	 * @param  OutputInterface $output
	 */
	protected function execute( InputInterface $input, OutputInterface $output ) {
		Connection::instance()->connect();

		$verbose = $input->getOption( 'verbose' );

		$create_s3 = Connection::instance()->s3->createBucket();

		$s3_setup = true;

		if ( Utils\is_error( $create_s3 ) ) {

			if ( 0 === $create_s3->code ) {
				$output->writeln( '<comment>Access denied. Could not read AWS buckets. S3 may already be setup.</comment>' );
			} elseif ( 1 === $create_s3->code ) {
				$output->writeln( '<comment>S3 already setup.</comment>' );
			} else {
				if ( 'BucketAlreadyOwnedByYou' === $e->getAwsErrorCode() || 'BucketAlreadyExists' === $e->getAwsErrorCode() ) {
					$output->writeln( '<comment>S3 already setup.</comment>' );
				} else {
					$output->writeln( '<error>Could not create S3 bucket.</error>' );
					$s3_setup = false;

					if ( $verbose ) {
						$output->writeln( '<error>Error Message: ' . $create_s3->data['message'] . '</error>' );
						$output->writeln( '<error>AWS Request ID: ' . $create_s3->data['aws_request_id'] . '</error>' );
						$output->writeln( '<error>AWS Error Type: ' . $create_s3->data['aws_error_type'] . '</error>' );
						$output->writeln( '<error>AWS Error Code: ' . $create_s3->data['aws_error_code'] . '</error>' );
					}
				}
			}
		}

		$create_db = Connection::instance()->db->createTables();

		$db_setup  = true;

		if ( Utils\is_error( $create_db ) ) {
			if ( 'ResourceInUseException' === $create_db->data['aws_error_code'] ) {
				$output->writeln( '<comment>DynamoDB table already setup.</comment>' );
			} else {
				$output->writeln( '<error>Could not create DynamoDB table.</error>' );
				$db_setup = false;

				if ( $verbose ) {
					$output->writeln( '<error>Error Message: ' . $create_db->data['message'] . '</error>' );
					$output->writeln( '<error>AWS Request ID: ' . $create_db->data['aws_request_id'] . '</error>' );
					$output->writeln( '<error>AWS Error Type: ' . $create_db->data['aws_error_type'] . '</error>' );
					$output->writeln( '<error>AWS Error Code: ' . $create_db->data['aws_error_code'] . '</error>' );
				}
			}
		}

		if ( ! $db_setup || ! $s3_setup ) {
			$output->writeln( '<error>Repository could not be created.</error>' );
		} else {
			$output->writeln( '<info>Repository setup!</info>' );
		}
	}

}
