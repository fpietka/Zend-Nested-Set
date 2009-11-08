#!/bin/sh
# load.sh - A simple bash database loader
#
# Usage: load.sh (struct|default|sample|reset|index|test|clean|update|upgrade|optimize|backup|restore|install|help) <db_name> <db_username> [host_name] [option]
#
# @copyright  Copyright (c) 2009 Nextcode
# @author     François Pietka
# @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)


# Path
if [ -d ./sql ]; then
   ROOT_DIR=`pwd`/sql
elif [ -f ./load.sh ]; then
   ROOT_DIR=`pwd`
else
   echo "$0 must run from base application directory !"
   exit 1
fi

#
# Paths
#
SCHEMA_DIR=$ROOT_DIR/schema
UPGRADE_DIR=$SCHEMA_DIR/upgrade
DATA_DIR=$ROOT_DIR/data
SAMPLE_DIR=$DATA_DIR/sample
DEFAULT_DIR=$DATA_DIR/default
TRUNCATE_DIR=$DATA_DIR/truncate

#
# Arguments
#
action="$1"
shift

db_name="$1"
shift

db_username="$1"
shift

db_hostname="$1"
if [ -z "${db_hostname}" ]; then
    db_hostname=localhost
else
    shift
fi

options="$1"
shift

#
# Check if database name provided
#
check_params()
{
    if [ -z "${db_name}" ]; then
        err 1 "No database name specified"
    fi

    if [ -z "${db_username}" ]; then
        err 1 "No database username specified"
    fi
}

#
# Create tables
#
struct_action()
{
    check_params

    # remove struct
    import_file $SCHEMA_DIR/clean.sql "Empty db: created" "Can't create empty db"

    # load struct
    import_file $SCHEMA_DIR/struct.sql "Database structure: loaded" "Can't load structure"
}

#
# Load default data to database
#
default_action()
{
    check_params

    notice "Load Default data:"

    FILE_LIST="`ls $DEFAULT_DIR | grep \"\.sql$\"`"

    for file in ${FILE_LIST}
    do
    import_file ${DEFAULT_DIR}/${file} "- Load \"${DEFAULT_DIR}/${file}\"" "Can't load default data"
    done

    notice "Done"
}

#
# Load default data to database
#
truncate_action()
{
    check_params

    notice "Truncate table(s):"

    FILE_LIST="`ls $TRUNCATE_DIR | grep \"\.sql$\"`"

    for file in ${FILE_LIST}
    do
    import_file ${TRUNCATE_DIR}/${file} "- Load \"${TRUNCATE_DIR}/${file}\"" "Can't load default data"
    done

    notice "Done"
}

#
# Load sample data to database
#
sample_action()
{
    check_params

    notice "Load Sample data:"

    FILE_LIST="`ls $SAMPLE_DIR | grep \"\.sql$\"`"

    for file in ${FILE_LIST}
    do
        import_file ${SAMPLE_DIR}/${file} "- Load \"${SAMPLE_DIR}/${file}\"" "Can't load sample data"
    done

    notice "Done"

}

#
# Create primary and foreighn keys and indexes
#
index_action()
{
    check_params

    # load indexes
    import_file $SCHEMA_DIR/indexes.sql "Indexes and foreign keys: loaded" "Can't load indexes and foreign keys"
}

#
# Struct, default, sample, index commands together
#
reset_action()
{
    check_params
    truncate_action
    default_action
    sample_action
    index_action
}

#
# Struct, default, sample, index commands together
#
test_action()
{
    check_params
    struct_action
    default_action
    sample_action
    index_action
}

#
# Struct, default, index commands together
#
clean_action()
{
    check_params
    struct_action
    default_action
    index_action
}

#
# Upgrade database with upgrade patch
#
upgrade_action()
{
    check_params

    if [ -z "${options}" ]; then

        # get next version
        if [ -f $UPGRADE_DIR/current ]; then
            CURRENT_VERSION=`more $UPGRADE_DIR/current`
        else
            CURRENT_VERSION=0
            mkdir -p $UPGRADE_DIR
        fi

        NEW_VERSION=$(($CURRENT_VERSION + 1))
    else

        # get has params
        NEW_VERSION=$options
    fi

    if [ -f $UPGRADE_DIR/upgrade-$NEW_VERSION.sql ]; then

        # load upgrade
        import_file $UPGRADE_DIR/upgrade-$NEW_VERSION.sql "Upgrade database to version: $NEW_VERSION" "Unable to upgrade database to version $NEW_VERSION"

        # update version
        mkdir -p $UPGRADE_DIR
        echo $NEW_VERSION > $UPGRADE_DIR/current

        # next upgrade available
        if [ -f $UPGRADE_DIR/upgrade-$(($NEW_VERSION + 1)).sql ]; then
            upgrade_action
        fi

    else
        warn "No new upgrade available, current version is $CURRENT_VERSION"
    fi
}


#
# Generate schema of database
#
update_action()
{
    check_params

    if [ -f ${SCHEMA_DIR}/struct.sql ]; then

        echo -n "Overide existing database struct? [Y/n]"
        read confirm

        # default
        if [ -z "${confirm}" ]; then
            confirm=y
        fi

        if [ ${confirm} != 'y' ]; then
            warn "Update aborded by user"
            exit
        fi
    fi

    notice "Generate database schema:"

    set +e
    update_results="`pg_dump -i -h ${db_hostname} -U ${db_username} -o --no-acl -s -c ${db_name} -f ${SCHEMA_DIR}/struct.sql 2>&1 | grep 'pg_dump'`"
    set -e

    if [ -z "${update_results}" ]; then
        notice "done"
    else
        err 1 "Unable to generate database schema cause: \n\t${update_results}"
    fi
}

#
# Install load.sq tools in current directory
#       @todo manage upgrade
#
install_action()
{
    mkdir -p $SCHEMA_DIR
    mkdir -p $UPGRADE_DIR
    mkdir -p $DATA_DIR
    mkdir -p $SAMPLE_DIR
    mkdir -p $DEFAULT_DIR

    # create clean
    touch $SCHEMA_DIR/clean.sql

    # create indexes
    touch $SCHEMA_DIR/indexes.sql

    if [ -f $UPGRADE_DIR/current ]; then
        warn "Not overide current upgrade version!"
    else
        echo "0" > $UPGRADE_DIR/current
    fi

    # create struct
    update_action
}

#
# Optimize database data and indexes
#
optimize_action()
{
    check_params

    exec_query "VACUUM ANALYZE;" "Optimize database data and indexes: done" "Unable to Optimize database"
}

#
# Backup quikly database
#
backup_action()
{
    check_params

    if [ -z "${options}" ]; then
        err 1 "No file specified"
    fi

    echo -n "Backup database into \"${options}\" file ? [Y/n]"
    read confirm

    # default
    if [ -z "${confirm}" ]; then
        confirm=y
    fi

    if [ ${confirm} == 'y' ]; then
        notice "Processing backup database into \"${options}\":"

        set +e
        backup_results="`pg_dump -F tar -i -c -h ${db_hostname} -U ${db_username} -f ${options} ${db_name}  2>&1 | grep 'pg_dump'`"
        set -e

        if [ -z "${backup_results}" ]; then
            notice "done"
        else
            err 1 "Unable to backup database cause: \n\t${backup_results}"
        fi
    else
        warn "Backup aborded by user"
    fi
}

#
# Backup quikly database
#
restore_action()
{
    check_params

    if [ -z "${options}" ]; then
        err 1 "No file specified"
    fi

    echo -n "Restore database from \"${options}\" file ? [y/N]"
    read confirm

    # default
    if [ -z "${confirm}" ]; then
        confirm=n
    fi

    if [ ${confirm} == 'y' ]; then

        # remove struct
        import_file $SCHEMA_DIR/clean.sql "Empty db: created" "Can't create empty db"

        notice "Processing restore database from \"${options}\":"

        set +e
        restore_results="`pg_restore -c -h ${db_hostname} -U ${db_username} -d ${db_name} ${options} 2>&1 | grep 'pg_restore'`"
        set -e

        if [ -z "${restore_results}" ]; then
            notice "done"
        else
            err 1 "Unable to restore database cause: \n\t${restore_results}"
        fi

    else
        warn "Restore aborded by user"
    fi
}

#
# Import file to SQL and handle error
#    @todo add transaction support
#
import_file()
{
    file_name=$1
    shift

    success_msg=$1
    shift

    error_msg=$1
    shift

    set +e
    import_results="`psql -f ${file_name} -h ${db_hostname} ${db_name} ${db_username} 2>&1 | egrep 'FATAL|ERR'`"
    set -e

    if [ -z "${import_results}" ]; then
        notice $success_msg
    else
        err 1 "${error_msg} cause: \n\t${import_results}"
    fi
}

#
# Exec SQL and handle error
#    @todo add transaction support
#
exec_query()
{
    query=$1
    shift

    success_msg=$1
    shift

    error_msg=$1
    shift

    set +e
    query_results="`echo \"${query}\" | psql -h ${db_hostname} ${db_name} ${db_username} 2>&1 | egrep 'FATAL|ERR'`"
    set -e

    if [ -z "${query_results}" ]; then
        notice $success_msg
    else
        err 1 "${error_msg} cause: \n\t${query_results}"
    fi
}

#
# Display help
#
usage()
{
    echo "Usage:"
    echo "  load.sh (struct|default|sample|reset|index|test|clean|update|upgrade|optimize|backup|restore|install|help) <db_name> <db_username> [host_name] [option]"
    echo "where:"
    echo "  struct      - create tables"
    echo "  default     - load default data to database"
    echo "  sample      - load sample data to database"
    echo "  reset       - truncate table(s) and load sample data to database"
    echo "  index       - create primary and foreighn keys and indexes"
    echo "  test        - struct, default, sample, index commands together"
    echo "  clean       - struct, default, index commands together"
    echo "  update      - update schema file"
    echo "  upgrade     - upgrade to next db patch or set path number with <option> param"
    echo "  optimize    - optimize database data and indexes"
    echo "  backup      - backup database as <option> file"
    echo "  restore     - restore database from <option> file"
    echo "  install     - create directory require by this script"
    echo "  help        - this help message"
}

#
# err exitval message
#    Display message to stderr and exit with exitval.
#
err()
{
        exitval=$1
        shift
        #echo -e 1>&2 "*** ERROR  : $*"
        echo -e 1>&2 '\E[31m'"\033[1m$*\033[0m"
        exit $exitval
}

#
# warn message
#    Display message to stdr
#
warn()
{
        #echo 1>&2 "*** WARNING: $*"
        echo -e 1>&2 '\E[33m'"\033[1m$*\033[0m"
}

#
# notice message
#   Display message to stdr.
#
notice()
{
    #echo -e 1>&2 "*** NOTICE : $*"
    echo -e 1>&2 "$*"
}

# debug
if [ "${action}" ]; then
    warn "Execute action \"$action\" on database \"$db_name\" as \"$db_username\" user on \"$db_hostname\" server"
fi

# bootstrap
case "${action}" in

    # deploy and test actions
    struct)     struct_action;;
    sample)     sample_action;;
    reset)      reset_action;;
    index)      index_action;;
    test)       test_action;;
    default)    default_action;;
    clean)      clean_action;;
    upgrade)    upgrade_action;;
    optimize)   optimize_action;;

    # maintenance actions
    backup)     backup_action;;
    restore)    restore_action;;
    update)     update_action;;
    install)    install_action;;
    *)          usage;;
esac
