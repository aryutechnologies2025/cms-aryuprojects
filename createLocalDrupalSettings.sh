#! /usr/bin/env bash

if [ -z "${MYSQL_USER}" ]; then
    MYSQL_USER="root"
fi
if [ -z "${MYSQL_PASSWORD}" ]; then
    MYSQL_PASSWORD="root"
fi
if [ -z "${MYSQL_HOST}" ]; then
    MYSQL_HOST="localhost"
fi

spinner()
{
    local pid=$1
    local delay=0.75
    local spinstr='|/-\'
    tput civis
    while [ "$(ps a | awk '{print $1}' | grep $pid)" ]; do
        local temp=${spinstr#?}
        printf " [%c]  " "$spinstr"
        local spinstr=$temp${spinstr%"$temp"}
        sleep $delay
        printf "\b\b\b\b\b\b"
    done
    tput cnorm
    printf "    \b\b\b\b"
}

trap 'tput cnorm' EXIT;

DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" >/dev/null 2>&1 && pwd )"

DB="sprowt3"
settings_file=${DIR}/sites/default/settings.php;
default_settings_file=${DIR}/sites/default/default.settings.php;
settings_file_tmp=${DIR}/sites/default/settings.php-tmp;
local_settings=${DIR}/sites/default/settings.local.php;
local_settings_template=${DIR}/sites/example.settings.local.php;
install_log=${DIR}/install.log;

chmod 755 ${DIR}/sites/default
echo "/sites/default/settings.local.php not found. Setting up local settings file... This might take a bit...";
mv $settings_file $settings_file_tmp;
drush si minimal --db-url=mysql://$MYSQL_USER:$MYSQL_PASSWORD@$MYSQL_HOST/$DB -y > $install_log 2>&1 & spinner $! || true;
if [ -f $settings_file ]; then
    rm $install_log;
    chmod 755 ${DIR}/sites/default
    chmod 777 $settings_file;
    cp -f $local_settings_template $local_settings;
    echo "" >> $local_settings;
    echo "" >> $local_settings;
    echo "/**=====================================" >> $local_settings;
    echo "*            local install" >> $local_settings;
    echo "*=====================================**/" >> $local_settings;
    echo "" >> $local_settings;
    echo "" >> $local_settings;
    diff "${default_settings_file}" "${settings_file}" | grep ">" | sed 's/^>//g' | sed 's/^\( *\)\(.\)/\1\1\2/g' >> "${local_settings}";
    rm -f $settings_file;
    mv -f $settings_file_tmp $settings_file;
else
    echo "Something went wrong...";
    cat $install_log;
    exit 1;
fi
