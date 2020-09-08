#!/usr/bin/python3
"""This script shall be used to create a new release.

It consists of several steps and updates the version number in the set configuration
file, creates a commit and a tag in git and finally builds and pushes a docker image to docker hub.

STEP 1 - Update version:
A parameter can be passed which can be one of the following: 'major'/'minor'/'patch'.
Depending on this parameter the version of the software will be updated in the
corresponding file. The file which holds this information can be set at the top
of the script with the VERSION_FILE variable and the regular expression to find
the string can be set as well.
When no parameter is given the version is not updated and subsequent steps use
the version string read from the file.

STEP 2 - Git commit and tag
Several steps are taken to update the git repository. The shell commands are the following:
    git add {VERSION_FILE}
    git commit -m "Update version to {new_version}"
    git push
    git tag {new_version}
    git push origin {new_version}

STEP 3 - Docker Image:
A docker image is built and tagged with the (new) version.
"docker build --target prod -t iqbberlin/testcenter-backend:{new_version} -f docker/Dockerfile ."

And subsequently pushed to dockerhub registry.
"docker push iqbberlin/testcenter-backend:{new_version}"
"""
import sys
import re
import subprocess

VERSION_FILE = 'composer.json'
VERSION_REGEX = '(?<=version": ")(.*)(?=")'


def _parse_version() -> str:
    match = pattern.search(file_content)
    if match:
        return match.group()
    else:
        sys.exit('Version pattern not found in file. Check your regex!')


def _update_version_in_file(new_version):
    new_file_content = pattern.sub(new_version, file_content)
    with open(VERSION_FILE, 'w') as f:
        f.write(new_file_content)


def _increment_version(old_version):
    version_part = sys.argv[1]
    old_version_as_list = old_version.split('.')
    if version_part == 'major':
        new_version = f'{int(old_version_as_list[0]) + 1}.0.0'
    elif version_part == 'minor':
        new_version = f'{old_version_as_list[0]}.{int(old_version_as_list[1]) + 1}.0'
    else:
        new_version = f'{old_version_as_list[0]}.{old_version_as_list[1]}.{int(old_version_as_list[2]) + 1}'
    return new_version


def _git_tag():
    print(f"Creating git tag for version {new_version}")
    subprocess.run(f"git add {VERSION_FILE}", shell=True, check=True)
    subprocess.run(f"git commit -m \"Update version to {new_version}\"", shell=True, check=True)
    subprocess.run("git push", shell=True, check=True)
    subprocess.run(f"git tag {new_version}", shell=True, check=True)
    subprocess.run(f"git push origin {new_version}", shell=True, check=True)


def _build_docker_image():
    print("Building Docker Image")
    subprocess.run(f"docker build --target prod -t iqbberlin/testcenter-backend:{new_version} -f docker/Dockerfile .",
                   shell=True, check=True)


def _push_docker_image():
    subprocess.run(f"docker push iqbberlin/testcenter-backend:{new_version}", shell=True, check=True)


pattern = re.compile(VERSION_REGEX)
with open(VERSION_FILE) as version_file:
    file_content = version_file.read()
    old_version = _parse_version()
if len(sys.argv) < 2:
    print("No parameter (major/minor/patch) given. Using version as found.")
    new_version = old_version
else:
    new_version = _increment_version(old_version)
    _update_version_in_file(new_version)
_git_tag()
_build_docker_image()
_push_docker_image()
