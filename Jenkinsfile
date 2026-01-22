pipeline {
    agent any

    environment {
        DEPLOY_PATH = "/var/www/cms-aryuprojects"
    }

    options {
        timestamps()
        disableConcurrentBuilds()
    }

    stages {

        stage('Verify Environment') {
            steps {
                sh '''
                whoami
                pwd
                '''
            }
        }

        stage('Fetch Latest Code') {
            steps {
                sh '''
                cd $DEPLOY_PATH
                git fetch origin
                git reset --hard origin/main
                '''
            }
        }

        stage('Install Dependencies') {
            steps {
                sh '''
                cd $DEPLOY_PATH
                if [ -f composer.json ]; then
                    composer install --no-dev --optimize-autoloader
                fi
                '''
            }
        }

        stage('Fix Permissions') {
            steps {
                sh '''
                chown -R www-data:www-data $DEPLOY_PATH
                find $DEPLOY_PATH -type d -exec chmod 755 {} \\;
                find $DEPLOY_PATH -type f -exec chmod 644 {} \\;
                '''
            }
        }

        stage('Reload Nginx') {
            steps {
                sh '''
                systemctl reload nginx
                '''
            }
        }
    }

    post {
        success {
            echo "Deployment successful"
        }
        failure {
            echo "Deployment FAILED"
        }
    }
}
