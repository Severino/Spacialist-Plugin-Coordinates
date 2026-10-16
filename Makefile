root_dir=.
dst_dir=./dist
build_dir=./build
build_src=./build/src
package_name=$(shell node -p -e "require('./package.json').name")
package_root=$(shell node -p -e "require('./package.json').pluginName")
script_name=$(shell node -p -e "require('./package.json').pluginName.toLowerCase()").umd.js
plugin_dir=$(build_src)/$(package_root)

# Everything that goes into the package. Keep these lists up to date:
# add every folder or file you create that the plugin needs at runtime.
backend_dirs=Attributes Controllers Listeners Models Services migrations routes
package_files=plugin.xml

# all steps for an appstore release
release: npm_init npm_build package

prebuild_release: clean package

npm_init:
	npm install

npm_build:
	npx vite build

# remove build dir
clean:
	rm -rf $(build_dir)

package: clean
	@[ -f $(dst_dir)/$(script_name) ] || (echo "Missing $(dst_dir)/$(script_name), run 'make npm_build' first" && exit 1)
	mkdir -p $(plugin_dir)/js
	@for dir in $(backend_dirs); do \
		cp -R $$dir $(plugin_dir)/ || exit 1; \
	done
	@for file in $(package_files); do \
		mkdir -p $(plugin_dir)/$$(dirname $$file) && cp $$file $(plugin_dir)/$$file || exit 1; \
	done
	cp $(dst_dir)/$(script_name) $(plugin_dir)/js/script.js
	tar -czf $(build_dir)/$(package_name).tar.gz \
	   --directory="$(build_src)" $(package_root)
	(cd $(build_src) && zip ../$(package_name).zip -r .)
	rm -rf $(build_src)
